<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CutoffNotice;
use App\Models\MaintenanceFee;
use App\Models\Meter;
use App\Models\MeterReplacement;
use App\Models\MonthlyDueDate;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\Reading;
use App\Models\Receipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    /**
     * Enregistre le relevé du mois et calcule le montant :
     * (nouvel index - index du mois antérieur) x prix du m³, plus les frais
     * d'entretien si ce mois en a.
     */
    public function recordReading(Meter $meter, string $period, float $currentIndex, string $readAt, ?string $notes): Reading
    {
        return DB::transaction(function () use ($meter, $period, $currentIndex, $readAt, $notes) {
            $this->lockOrganisation();
            $meter = Meter::whereKey($meter->id)->lockForUpdate()->firstOrFail();

            if (! $meter->customer_id) {
                $this->fail('meter_id', __('Ce compteur n\'est rattaché à aucun client.'));
            }

            $unitPrice = $this->priceFor(Customer::find($meter->customer_id));
            if ($unitPrice === null) {
                $this->fail('current_index', __('Renseignez d\'abord le prix du mètre cube dans les paramètres ou sur la fiche du client.'));
            }

            if (! $meter->is_active) {
                $this->fail('meter_id', __('Ce compteur est désactivé.'));
            }

            $last = $meter->latestReading();
            if ($last && $period <= $last->period) {
                $this->fail('period', __('Le dernier relevé de ce compteur est celui ').of_period($last->period).__('. Choisissez un mois ultérieur.'));
            }

            $lastDebt = $meter->lastOpeningPeriod();
            if ($lastDebt && $period <= $lastDebt) {
                $this->fail('period', __('Ce compteur a une dette antérieure jusqu\'à ').period_label($lastDebt).__('. Choisissez un mois ultérieur.'));
            }

            $previousIndex = $meter->lastIndex();
            $this->assertIndex($currentIndex, $previousIndex);

            $fee = (float) MaintenanceFee::where('period', $period)->value('amount');

            // Le premier relevé d'un compteur de remplacement inclut la fin de l'ancien compteur.
            $replacement = MeterReplacement::where('new_meter_id', $meter->id)->first();
            if ($replacement && $period < $replacement->replaced_on->format('Y-m')) {
                $this->fail('period', __('Ce compteur a été posé en ').period_label($replacement->replaced_on->format('Y-m')).__('. Choisissez ce mois ou un mois ultérieur.'));
            }
            $replacement = $replacement && ! $replacement->reading()->exists() ? $replacement : null;
            $carried = (float) $replacement?->old_consumption;

            return Reading::create([
                'meter_id' => $meter->id,
                'customer_id' => $meter->customer_id,
                'user_id' => Auth::id(),
                'period' => $period,
                'read_at' => $readAt,
                'due_date' => $this->monthlyDueDate($period) ?? $this->dueDate($readAt),
                'previous_index' => $previousIndex,
                'current_index' => $currentIndex,
                'notes' => $notes,
                'unit_price' => $unitPrice,
                'maintenance_fee' => $fee,
                'meter_replacement_id' => $replacement?->id,
                'carried_consumption' => $carried,
            ] + $this->computeAmounts($previousIndex, $currentIndex, $unitPrice, $fee, $carried));
        });
    }

    /**
     * Corrige une erreur de saisie sur n'importe quel relevé. Le relevé suivant part de cet
     * index : il est recalculé en même temps. Rien n'est enregistré si un montant devenait
     * inférieur à ce qui a déjà été payé.
     *
     * @param  array{current_index: float, read_at: string, notes: ?string, period?: ?string, due_date?: ?string, unit_price?: ?float}  $data
     */
    public function correctReading(Reading $reading, array $data): Reading
    {
        return DB::transaction(function () use ($reading, $data) {
            $this->lockOrganisation();
            $reading = Reading::whereKey($reading->id)->lockForUpdate()->firstOrFail();

            if ($reading->is_opening) {
                $this->fail('current_index', __('Une dette antérieure se modifie depuis la rubrique Dettes antérieures.'));
            }

            $meter = $reading->meter;
            $others = $meter->readings()->real()->whereKeyNot($reading->id)->orderBy('period')->lockForUpdate()->get();
            $before = $others->last(fn (Reading $other) => $other->period < $reading->period);
            $after = $others->first(fn (Reading $other) => $other->period > $reading->period);

            $period = $data['period'] ?? $reading->period;
            $periodChanged = $period !== $reading->period;
            if ($periodChanged) {
                $lastDebt = $meter->lastOpeningPeriod();
                if (($before && $period <= $before->period) || ($after && $period >= $after->period) || ($lastDebt && $period <= $lastDebt)) {
                    $this->fail('period', __('Ce mois doit rester entre le relevé précédent et le relevé suivant de ce compteur.'));
                }
            }

            $currentIndex = (float) $data['current_index'];
            $this->assertIndex($currentIndex, (float) $reading->previous_index);
            if ($after && $currentIndex > (float) $after->current_index) {
                $this->fail('current_index', __('Le nouvel index ne peut pas dépasser celui ').of_period($after->period).' ('.qty($after->current_index).__('). Corrigez d\'abord ce relevé.'));
            }

            $unitPrice = (float) ($data['unit_price'] ?? $reading->unit_price);
            $fee = $periodChanged ? (float) MaintenanceFee::where('period', $period)->value('amount') : (float) $reading->maintenance_fee;
            $amounts = $this->computeAmounts((float) $reading->previous_index, $currentIndex, $unitPrice, $fee, (float) $reading->carried_consumption);
            $this->assertCoversPayments($reading, $amounts['amount']);

            $reading->update([
                'period' => $period,
                'current_index' => $currentIndex,
                'read_at' => $data['read_at'],
                'due_date' => $this->correctedDueDate($reading, $period, $data),
                'notes' => $data['notes'] ?? null,
                'unit_price' => $unitPrice,
                'maintenance_fee' => $fee,
            ] + $amounts);

            if ($after) {
                $this->rebaseReading($after, $currentIndex);
            } elseif ($replacement = MeterReplacement::where('old_meter_id', $meter->id)->first()) {
                // Dernier relevé d'un compteur remplacé : la consommation reportée en dépend.
                $this->refreshReplacement($replacement, $currentIndex, (float) $replacement->old_final_index, 'current_index');
            }

            return $reading;
        });
    }

    // Corrige l'index de départ d'un compteur et recalcule son premier relevé.
    public function correctInitialIndex(Meter $meter, float $initialIndex): void
    {
        DB::transaction(function () use ($meter, $initialIndex) {
            $this->lockOrganisation();
            $meter->readings()->where('is_opening', true)->update(['previous_index' => $initialIndex, 'current_index' => $initialIndex]);
            $first = $meter->readings()->real()->orderBy('period')->lockForUpdate()->first();

            if (! $first) {
                if ($replacement = MeterReplacement::where('old_meter_id', $meter->id)->first()) {
                    $this->refreshReplacement($replacement, $initialIndex, (float) $replacement->old_final_index, 'initial_index');
                }

                return;
            }

            if ($initialIndex > (float) $first->current_index) {
                $this->fail('initial_index', __('L\'index de départ ne peut pas dépasser celui ').of_period($first->period).' ('.qty($first->current_index).').');
            }

            $this->rebaseReading($first, $initialIndex, 'initial_index');
        });
    }

    /**
     * Fixe l'échéance de toutes les factures d'un mois, ou revient au délai habituel si
     * $dueDate est null.
     *
     * @return int le nombre de factures du mois
     */
    public function setMonthlyDueDate(string $period, ?string $dueDate): int
    {
        return DB::transaction(function () use ($period, $dueDate) {
            $this->lockOrganisation();
            $readings = Reading::real()->where('period', $period)->lockForUpdate()->get();

            foreach ($readings as $reading) {
                $reading->update(['due_date' => $dueDate ?? $this->dueDate($reading->read_at->toDateString())]);
            }

            if ($dueDate === null) {
                MonthlyDueDate::where('period', $period)->delete();
            } else {
                MonthlyDueDate::updateOrCreate(['period' => $period], ['due_date' => $dueDate]);
            }

            return $readings->count();
        });
    }

    /**
     * Applique le tarif actuel de ces clients à leurs factures d'un mois. Une facture déjà
     * payée au-delà du nouveau montant est laissée telle quelle.
     *
     * @return array{updated: int, skipped: int}
     */
    public function repriceCustomers(iterable $customerIds, string $period): array
    {
        return DB::transaction(function () use ($customerIds, $period) {
            $this->lockOrganisation();
            $result = ['updated' => 0, 'skipped' => 0];

            $readings = Reading::real()->with('customer')->where('period', $period)
                ->whereIn('customer_id', $customerIds)->lockForUpdate()->get();

            foreach ($readings as $reading) {
                $unitPrice = $this->priceFor($reading->customer);
                $amounts = $this->computeAmounts((float) $reading->previous_index, (float) $reading->current_index, (float) $unitPrice, (float) $reading->maintenance_fee, (float) $reading->carried_consumption);

                if ($unitPrice === null || (float) $reading->paid_amount > $amounts['amount']) {
                    $result['skipped']++;

                    continue;
                }

                $reading->update(['unit_price' => $unitPrice] + $amounts);
                $result['updated']++;
            }

            return $result;
        });
    }

    /**
     * Remplace un compteur chez son client. Le nouveau reprend sa zone et sa position, et
     * l'ancien est désactivé. Ce qu'il a consommé depuis son dernier relevé est gardé pour
     * la prochaine facture du nouveau compteur : le mois tient sur une seule facture.
     *
     * @param  array{replaced_on: string, old_final_index: float|string, number: string, initial_index: float|string, notes?: ?string}  $data
     */
    public function replaceMeter(Meter $meter, array $data): MeterReplacement
    {
        return DB::transaction(function () use ($meter, $data) {
            $this->lockOrganisation();
            $meter = Meter::whereKey($meter->id)->lockForUpdate()->firstOrFail();

            if (! $meter->customer_id) {
                $this->fail('old_final_index', __('Ce compteur n\'est rattaché à aucun client.'));
            }
            if (! $meter->is_active || MeterReplacement::where('old_meter_id', $meter->id)->exists()) {
                $this->fail('old_final_index', __('Ce compteur est désactivé ou déjà remplacé.'));
            }

            $pending = MeterReplacement::where('new_meter_id', $meter->id)->first();
            if ($pending && ! $pending->reading()->exists()) {
                $this->fail('old_final_index', __('Ce compteur vient lui-même d\'en remplacer un : saisissez d\'abord son premier relevé, ou annulez ce remplacement.'));
            }

            $last = $meter->latestReading();
            $replacedMonth = Carbon::parse($data['replaced_on'])->format('Y-m');
            if ($last && $replacedMonth < $last->period) {
                $this->fail('replaced_on', __('Le dernier relevé de ce compteur est celui ').of_period($last->period).__(' : le remplacement ne peut pas être antérieur.'));
            }

            $previous = $meter->lastIndex();
            $final = (float) $data['old_final_index'];
            if ($final < $previous) {
                $this->fail('old_final_index', __('L\'index final ne peut pas être inférieur au dernier index (').qty($previous).').');
            }

            $new = Meter::create([
                'customer_id' => $meter->customer_id,
                'zone_id' => $meter->zone_id,
                'number' => $data['number'],
                'initial_index' => $data['initial_index'],
                'installed_at' => $data['replaced_on'],
                'latitude' => $meter->latitude,
                'longitude' => $meter->longitude,
                'is_active' => true,
            ]);

            $meter->update(['is_active' => false]);

            return MeterReplacement::create([
                'old_meter_id' => $meter->id,
                'new_meter_id' => $new->id,
                'customer_id' => $meter->customer_id,
                'user_id' => Auth::id(),
                'replaced_on' => $data['replaced_on'],
                'old_previous_index' => $previous,
                'old_final_index' => $final,
                'old_consumption' => round($final - $previous, 3),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function correctReplacement(MeterReplacement $replacement, array $data): void
    {
        DB::transaction(function () use ($replacement, $data) {
            $this->lockOrganisation();
            $replacement = MeterReplacement::whereKey($replacement->id)->lockForUpdate()->firstOrFail();

            $replacement->update(['replaced_on' => $data['replaced_on'], 'notes' => $data['notes'] ?? null]);
            $this->refreshReplacement($replacement, (float) $replacement->old_previous_index, (float) $data['old_final_index'], 'old_final_index');
        });
    }

    // Possible tant que le nouveau compteur n'a ni relevé, ni dette, ni avis.
    public function cancelReplacement(MeterReplacement $replacement): void
    {
        DB::transaction(function () use ($replacement) {
            $this->lockOrganisation();
            $new = $replacement->newMeter;

            if ($new->readings()->exists() || CutoffNotice::where('meter_id', $new->id)->exists()) {
                $this->fail('replacement', __('Le nouveau compteur a déjà un relevé : ce remplacement ne peut plus être annulé. Vous pouvez le corriger.'));
            }

            $old = $replacement->oldMeter;
            $replacement->delete();
            $new->delete();
            $old->update(['is_active' => true]);
        });
    }

    // Recalcule la consommation reportée, et la facture qui la contient si elle existe déjà.
    private function refreshReplacement(MeterReplacement $replacement, float $previous, float $final, string $field): void
    {
        if ($final < $previous) {
            $this->fail($field, __('L\'index de l\'ancien compteur ne peut pas dépasser son index final (').qty($final).').');
        }

        $consumption = round($final - $previous, 3);

        $replacement->update([
            'old_previous_index' => $previous,
            'old_final_index' => $final,
            'old_consumption' => $consumption,
        ]);

        $reading = $replacement->reading()->lockForUpdate()->first();
        if ($reading) {
            $reading->carried_consumption = $consumption;
            $this->rebaseReading($reading, (float) $reading->previous_index, $field);
        }
    }

    public function updatePayment(Payment $payment, float $amount, string $paidAt, ?string $method, ?string $reference): void
    {
        DB::transaction(function () use ($payment, $amount, $paidAt, $method, $reference) {
            $reading = Reading::whereKey($payment->reading_id)->lockForUpdate()->firstOrFail();
            $this->assertCurrencyDecimals($amount, 'amount');

            $available = round($reading->balance() + (float) $payment->amount, 3);
            if (round($amount, 3) > $available) {
                $this->fail('amount', __('Le montant dépasse le reste à payer (').money($available).').');
            }

            $payment->update([
                'amount' => $amount,
                'paid_at' => $paidAt,
                'method' => $method,
                'reference' => $reference,
            ]);

            $this->refreshPaidAmount($reading);
            $this->refreshReceipt($payment->receipt_id);
        });
    }

    /**
     * Reprend d'un ancien système la dette d'un mois sur ce compteur : une ligne par mois
     * impayé, car un client peut avoir payé un mois et pas le précédent. Elle reste au
     * client d'origine, comme une facture.
     */
    public function addOpeningBalance(Meter $meter, string $period, float $amount): Reading
    {
        return DB::transaction(function () use ($meter, $period, $amount) {
            $this->lockOrganisation();
            $meter = Meter::whereKey($meter->id)->lockForUpdate()->firstOrFail();

            if (! $meter->customer_id) {
                $this->fail('meter_id', __('Rattachez un client à ce compteur pour lui reprendre une dette.'));
            }

            $this->assertOpeningBalance($meter, $period, $amount);

            return Reading::create([
                'meter_id' => $meter->id,
                'customer_id' => $meter->customer_id,
                'user_id' => Auth::id(),
                'is_opening' => true,
                'period' => $period,
                'read_at' => $period.'-01',
                'due_date' => $this->dueDate($period.'-01'),
                'previous_index' => $meter->initial_index,
                'current_index' => $meter->initial_index,
                'consumption' => 0,
                'unit_price' => 0,
                'amount' => $amount,
            ]);
        });
    }

    public function updateOpeningBalance(Reading $balance, string $period, float $amount): void
    {
        DB::transaction(function () use ($balance, $period, $amount) {
            $this->lockOrganisation();
            $balance = Reading::whereKey($balance->id)->lockForUpdate()->firstOrFail();

            $this->assertOpeningBalance($balance->meter, $period, $amount, $balance);

            if ((float) $balance->paid_amount > round($amount, 3)) {
                $this->fail('opening_balance', __('Cette dette a déjà reçu ').money($balance->paid_amount).__(' de paiements : elle ne peut pas être inférieure.'));
            }

            $balance->update([
                'period' => $period,
                'read_at' => $period.'-01',
                'due_date' => $balance->period === $period ? $balance->due_date : $this->dueDate($period.'-01'),
                'amount' => $amount,
            ]);
        });
    }

    public function removeOpeningBalance(Reading $balance): void
    {
        DB::transaction(function () use ($balance) {
            if ($balance->payments()->exists()) {
                $this->fail('opening_balance', __('Cette dette a déjà reçu des paiements. Annulez-les avant de la retirer.'));
            }

            // Les paiements annulés restent en base et bloqueraient la suppression.
            $balance->payments()->onlyTrashed()->forceDelete();
            $balance->delete();
        });
    }

    private function assertOpeningBalance(Meter $meter, string $period, float $amount, ?Reading $current = null): void
    {
        $this->assertCurrencyDecimals($amount, 'opening_balance');

        $firstReading = $meter->readings()->real()->min('period');
        if ($firstReading && $period >= $firstReading) {
            $this->fail('opening_period', __('Ce mois doit être antérieur au premier relevé de ce compteur (').period_label($firstReading).').');
        }

        $duplicate = $meter->readings()->where('is_opening', true)->where('period', $period)
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))->exists();
        if ($duplicate) {
            $this->fail('opening_period', __('Ce compteur a déjà une dette pour ').period_label($period).__(' : modifiez-la dans la liste.'));
        }
    }

    /**
     * Fixe les frais d'entretien d'un mois, ou les retire si $amount est null, sur
     * toutes ses factures. Le montant remplace le précédent : il ne s'additionne pas.
     *
     * @return int le nombre de factures du mois
     */
    public function setMaintenanceFee(string $period, ?float $amount): int
    {
        return DB::transaction(function () use ($period, $amount) {
            $this->lockOrganisation();
            $decimals = Auth::user()->organisation->currencyDecimals();

            if ($amount !== null && round($amount, $decimals) !== round($amount, 3)) {
                $this->fail('maintenance_amount', $decimals === 0
                    ? __('Cette devise n\'accepte pas de décimales.')
                    : "Cette devise accepte {$decimals} décimales au maximum.");
            }

            $fee = (float) $amount;
            $readings = Reading::real()->where('period', $period)->lockForUpdate()->get();

            foreach ($readings as $reading) {
                $total = round((float) $reading->amount - (float) $reading->maintenance_fee + $fee, 3);

                if ((float) $reading->paid_amount > $total) {
                    $this->fail('maintenance_amount', __('Impossible : une facture ').of_period($period).__(' a déjà été payée au-delà de ce montant. Annulez d\'abord le paiement.'));
                }

                $reading->update(['maintenance_fee' => $fee, 'amount' => $total]);
            }

            if ($amount === null) {
                MaintenanceFee::where('period', $period)->delete();
            } else {
                MaintenanceFee::updateOrCreate(['period' => $period], ['amount' => $amount]);
            }

            return $readings->count();
        });
    }

    public function recordPayment(Reading $reading, float $amount, string $paidAt, ?string $method, ?string $reference): Payment
    {
        return DB::transaction(function () use ($reading, $amount, $paidAt, $method, $reference) {
            $reading = Reading::whereKey($reading->id)->lockForUpdate()->firstOrFail();
            $decimals = Auth::user()->organisation->currencyDecimals();

            if (round($amount, $decimals) !== round($amount, 3)) {
                $this->fail('amount', $decimals === 0
                    ? __('Cette devise n\'accepte pas de décimales.')
                    : "Cette devise accepte {$decimals} décimales au maximum.");
            }
            if (round($amount, 3) > $reading->balance()) {
                $this->fail('amount', __('Le montant dépasse le reste à payer (').money($reading->balance()).').');
            }

            $payment = $reading->payments()->create([
                'user_id' => Auth::id(),
                'amount' => $amount,
                'paid_at' => $paidAt,
                'method' => $method,
                'reference' => $reference,
            ]);

            $this->refreshPaidAmount($reading);

            return $payment;
        });
    }

    public function cancelPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $reading = Reading::whereKey($payment->reading_id)->lockForUpdate()->firstOrFail();
            $payment->delete();
            $this->refreshPaidAmount($reading);
            $this->refreshReceipt($payment->receipt_id);
        });
    }

    /**
     * Répartit un montant versé sur la dette du client (ou d'un seul de ses compteurs), en payant d'abord la
     * dette la plus ancienne : 10 000 versés sur 4 mois à 3 000 règlent les 3 plus anciens et laissent 2 000
     * sur le plus récent. À mois égal, le compteur au plus petit numéro passe d'abord.
     * Retourne [id du relevé => montant].
     */
    public function allocateOldestFirst(Customer $customer, ?int $meterId, float $amount): array
    {
        $readings = Reading::with('meter')->unpaid()->where('customer_id', $customer->id)
            ->when($meterId, fn ($query) => $query->where('meter_id', $meterId))->get()
            ->sortBy(fn (Reading $reading) => [$reading->period, $reading->meter->number, $reading->id])->values();

        if ($readings->isEmpty()) {
            $this->fail('amount', __('Ce client n\'a aucun mois impayé.'));
        }

        $amount = round($amount, 3);
        $this->assertCurrencyDecimals($amount, 'amount');
        $debt = round((float) $readings->sum(fn (Reading $reading) => $reading->balance()), 3);
        if ($amount <= 0) {
            $this->fail('amount', __('Saisissez le montant versé.'));
        }
        if ($amount > $debt) {
            $this->fail('amount', __('Le montant versé dépasse la dette (:v1).', ['v1' => money($debt)]));
        }

        $lines = [];
        $left = $amount;
        foreach ($readings as $reading) {
            if ($left <= 0) {
                break;
            }
            $part = min($left, $reading->balance());
            $lines[$reading->id] = round($part, 3);
            $left = round($left - $part, 3);
        }

        return $lines;
    }

    /**
     * Encaisse en une fois plusieurs mois choisis librement par le client, sur un ou plusieurs de ses
     * compteurs : [id du relevé => montant]. Un mois peut être réglé en entier ou en partie (acompte).
     * Tout passe ou rien : un seul mois en erreur annule l'encaissement entier.
     */
    public function recordReceipt(Customer $customer, array $lines, string $paidAt, ?string $method, ?string $reference): Receipt
    {
        return DB::transaction(function () use ($customer, $lines, $paidAt, $method, $reference) {
            $lines = collect($lines)->map(fn ($amount) => round((float) $amount, 3))->filter(fn (float $amount) => $amount > 0);
            if ($lines->isEmpty()) {
                $this->fail('lines', __('Cochez au moins un mois à encaisser.'));
            }

            $readings = Reading::with('meter')->whereKey($lines->keys()->all())
                ->where('customer_id', $customer->id)->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as $readingId => $amount) {
                $reading = $readings->get($readingId);
                if (! $reading) {
                    $this->fail('lines', __('Un des mois choisis n\'appartient pas à ce client.'));
                }

                $this->assertCurrencyDecimals($amount, "lines.$readingId");
                if ($amount > $reading->balance()) {
                    $this->fail("lines.$readingId", __(':v1 (compteur :v2) : le montant dépasse le reste à payer (:v3).', [
                        'v1' => $reading->label(), 'v2' => $reading->meter->number, 'v3' => money($reading->balance()),
                    ]));
                }
            }

            $receipt = Receipt::create([
                'customer_id' => $customer->id,
                'user_id' => Auth::id(),
                'total' => round($lines->sum(), 3),
                'paid_at' => $paidAt,
                'method' => $method,
                'reference' => $reference,
            ]);

            foreach ($lines as $readingId => $amount) {
                $reading = $readings->get($readingId);
                $reading->payments()->create([
                    'receipt_id' => $receipt->id,
                    'user_id' => Auth::id(),
                    'amount' => $amount,
                    'paid_at' => $paidAt,
                    'method' => $method,
                    'reference' => $reference,
                ]);
                $this->refreshPaidAmount($reading);
            }

            return $receipt;
        });
    }

    // Annule tout l'encaissement : chacun de ses mois retrouve ce qu'il restait à payer avant.
    public function cancelReceipt(Receipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $readingIds = $receipt->payments()->pluck('reading_id');
            $readings = Reading::whereKey($readingIds->all())->lockForUpdate()->get();

            $receipt->payments()->get()->each->delete();
            $readings->each(fn (Reading $reading) => $this->refreshPaidAmount($reading));
            $receipt->delete();
        });
    }

    // Un paiement d'un encaissement corrigé ou annulé à part : le total du reçu suit.
    private function refreshReceipt(?int $receiptId): void
    {
        $receipt = $receiptId ? Receipt::find($receiptId) : null;
        if (! $receipt) {
            return;
        }

        $total = round((float) $receipt->payments()->sum('amount'), 3);
        $total > 0 ? $receipt->update(['total' => $total]) : $receipt->delete();
    }

    private function computeAmounts(float $previousIndex, float $currentIndex, float $unitPrice, float $fee, float $carried = 0): array
    {
        $consumption = round($currentIndex - $previousIndex + $carried, 3);

        return [
            'consumption' => $consumption,
            'amount' => round(round($consumption * $unitPrice, Auth::user()->organisation->currencyDecimals()) + $fee, 3),
        ];
    }

    private function priceFor(Customer $customer): ?float
    {
        $price = $customer->price_per_m3 ?? Auth::user()->organisation->price_per_m3;

        return $price === null ? null : (float) $price;
    }

    // Recalcule un relevé dont l'index de départ a changé.
    private function rebaseReading(Reading $reading, float $previousIndex, string $errorField = 'current_index'): void
    {
        $amounts = $this->computeAmounts($previousIndex, (float) $reading->current_index, (float) $reading->unit_price, (float) $reading->maintenance_fee, (float) $reading->carried_consumption);
        $this->assertCoversPayments($reading, $amounts['amount'], $errorField);

        $reading->update(['previous_index' => $previousIndex] + $amounts);
    }

    private function assertCoversPayments(Reading $reading, float $amount, string $field = 'current_index'): void
    {
        if ((float) $reading->paid_amount > $amount) {
            $this->fail($field, __('La facture ').of_period($reading->period).__(' a déjà reçu ').money($reading->paid_amount).__(' de paiements : son montant ne peut pas descendre à ').money($amount).__('. Corrigez d\'abord le paiement.'));
        }
    }

    private function assertCurrencyDecimals(float $amount, string $field): void
    {
        $decimals = Auth::user()->organisation->currencyDecimals();

        if (round($amount, $decimals) !== round($amount, 3)) {
            $this->fail($field, $decimals === 0
                ? __('Cette devise n\'accepte pas de décimales.')
                : "Cette devise accepte {$decimals} décimales au maximum.");
        }
    }

    private function monthlyDueDate(string $period): ?string
    {
        return MonthlyDueDate::where('period', $period)->first()?->due_date->toDateString();
    }

    // Une date saisie est gardée telle quelle ; sinon l'échéance du mois, ou le même délai qu'avant.
    private function correctedDueDate(Reading $reading, string $period, array $data): string
    {
        if (! empty($data['due_date'])) {
            return $data['due_date'];
        }

        $delay = (int) $reading->read_at->diffInDays($reading->due_date);

        return $this->monthlyDueDate($period) ?? Carbon::parse($data['read_at'])->addDays($delay)->toDateString();
    }

    private function dueDate(string $readAt): string
    {
        return Carbon::parse($readAt)->addDays(Auth::user()->organisation->payment_due_days)->toDateString();
    }

    // Un relevé saisi pendant un changement de frais ne doit pas passer à travers.
    private function lockOrganisation(): void
    {
        Organisation::whereKey(Auth::user()->organisation_id)->lockForUpdate()->first();
    }

    private function refreshPaidAmount(Reading $reading): void
    {
        $reading->update(['paid_amount' => $reading->payments()->sum('amount')]);
    }

    private function assertIndex(float $currentIndex, float $previousIndex): void
    {
        if ($currentIndex < $previousIndex) {
            $this->fail('current_index', __('Le nouvel index ne peut pas être inférieur à l\'index précédent (').qty($previousIndex).').');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
