<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CutoffNotice;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\Reading;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Indicateurs du tableau de bord. Tout est calculé à partir des données saisies, avec des
 * règles simples et vérifiables : aucune prévision n'est présentée comme un fait.
 */
class DashboardService
{
    /**
     * Vue générale : la situation d'aujourd'hui, toutes dettes confondues.
     * Vue mensuelle ou annuelle : seulement les factures et les paiements de la période choisie.
     */
    public function build(?string $view = null, ?string $month = null, ?string $year = null): array
    {
        $scope = $this->scope($view, $month, $year);
        $filtered = $scope['view'] !== 'general';
        $period = $scope['to'];
        $previous = now()->subMonthNoOverflow()->format('Y-m');

        $inScope = fn ($query) => $query->when($filtered, fn ($q) => $q->whereBetween('period', [$scope['from'], $scope['to']]));

        $unpaid = $inScope(Reading::unpaid())->with(['customer', 'meter.zone'])->get();
        $totalUnpaid = round($unpaid->sum(fn (Reading $reading) => $reading->balance()), 3);
        $overdue = $unpaid->filter(fn (Reading $reading) => $reading->isOverdue());

        $months = $this->months();
        $chartMonths = match ($scope['view']) {
            'annee' => $this->months(Carbon::createFromFormat('!Y-m', $scope['from'])),
            'mois' => $this->visibleMonths($this->months(Carbon::createFromFormat('!Y-m', $scope['from'])->subMonths(11))),
            default => $this->visibleMonths($months),
        };
        $current = $this->totalsFor($scope['from'], $scope['to']);
        $last = $this->totalsFor($scope['previousFrom'], $scope['previousTo']);

        // Un compteur posé après le mois regardé n'avait pas à être relevé ce mois-là.
        $pendingCount = $scope['view'] === 'annee' ? null : Meter::where('is_active', true)->whereNotNull('customer_id')
            ->where('created_at', '<=', Carbon::createFromFormat('!Y-m', $period)->endOfMonth())
            ->whereDoesntHave('readings', fn ($q) => $q->real()->where('period', $period))->count();

        $customersCount = Customer::count();
        $debtors = $this->debtors($unpaid, $totalUnpaid);
        $aging = $this->aging($unpaid, $totalUnpaid);
        $zones = $this->zones($unpaid, $totalUnpaid, $scope['from'], $scope['to']);
        $anomalies = $filtered ? collect() : $this->anomalies($previous);
        $dueSoon = $unpaid->filter(fn (Reading $reading) => ! $reading->isOverdue()
            && $reading->due_date?->lte(today()->addDays(config('dashboard.due_soon_days'))));

        $totals = $inScope(Reading::query())->selectRaw('COALESCE(SUM(amount), 0) as billed, COALESCE(SUM(paid_amount), 0) as paid')->first();
        $overdueAmount = round($overdue->sum(fn (Reading $reading) => $reading->balance()), 3);
        $customersInGoodStanding = max(0, $customersCount - $unpaid->pluck('customer_id')->unique()->count());

        return [
            'rings' => [
                $this->recoveryRing((float) $totals->billed, (float) $totals->paid),
                $this->customersRing($customersCount, $customersInGoodStanding),
                ...($totalUnpaid > 0 ? [$this->agingRing($aging, $totalUnpaid, $overdueAmount)] : []),
                ...($totalUnpaid > 0 && $zones->isNotEmpty() ? [$this->zonesRing($zones)] : []),
            ],
            'scope' => $scope,
            'years' => $this->years(),
            'period' => $period,
            'previous' => $previous,
            'customersCount' => $customersCount,
            'metersCount' => Meter::count(),
            'pendingCount' => $pendingCount,
            'billed' => $current['billed'],
            'lastBilled' => $last['billed'],
            'collected' => $current['collected'],
            'lastCollected' => $last['collected'],
            'volume' => $current['volume'],
            'lastVolume' => $last['volume'],
            'totalUnpaid' => $totalUnpaid,
            'overdueAmount' => $overdueAmount,
            'recoveryRate' => (float) $totals->billed > 0 ? (int) round($totals->paid / $totals->billed * 100) : null,
            'customersInGoodStanding' => $customersInGoodStanding,
            'months' => $chartMonths,
            'chartTicks' => $this->ticks($chartMonths->flatMap(fn ($month) => [$month['billed'], $month['collected']])->max() ?? 0),
            'aging' => $aging,
            'debtors' => $debtors->take(5),
            'zones' => $zones,
            // Les écarts les plus marqués d'abord, en nombre limité : la liste ne grossit pas avec le réseau.
            'anomalies' => $anomalies->sortByDesc(fn ($anomaly) => abs($anomaly->consumption - $anomaly->average) / $anomaly->average)
                ->take((int) config('dashboard.anomalies_shown'))->values(),
            'anomalyCount' => $anomalies->count(),
            'actions' => $filtered ? [] : $this->actions($pendingCount, $dueSoon, $anomalies->count(), $period),
            'insights' => $filtered ? [] : $this->insights($debtors, $aging, $zones, $months, $dueSoon, $previous),
            'latestPayments' => Payment::with('reading.customer')
                ->when($filtered, fn ($q) => $q->whereBetween('paid_at', $this->dateBounds($scope['from'], $scope['to'])))
                ->latest('paid_at')->latest('id')->limit(5)->get(),
        ];
    }

    // Lit la période demandée ; une valeur invalide ou future revient au mois ou à l'année en cours.
    private function scope(?string $view, ?string $month, ?string $year): array
    {
        $currentMonth = now()->format('Y-m');

        if ($view === 'annee') {
            $selected = preg_match('/^(19|20)\d{2}$/', (string) $year) && (int) $year <= now()->year ? (int) $year : now()->year;

            return [
                'view' => 'annee', 'from' => $selected.'-01', 'to' => $selected.'-12',
                'previousFrom' => ($selected - 1).'-01', 'previousTo' => ($selected - 1).'-12',
                'label' => (string) $selected, 'compareLabel' => (string) ($selected - 1),
                'unpaidTitle' => __('Impayés de ').$selected, 'value' => (string) $selected,
            ];
        }

        $selected = $view === 'mois' && preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])$/', (string) $month) && $month <= $currentMonth
            ? $month : $currentMonth;
        $before = Carbon::createFromFormat('!Y-m', $selected)->subMonth()->format('Y-m');

        return [
            'view' => $view === 'mois' ? 'mois' : 'general', 'from' => $selected, 'to' => $selected,
            'previousFrom' => $before, 'previousTo' => $before,
            'label' => period_label($selected), 'compareLabel' => period_label($before),
            'unpaidTitle' => $view === 'mois' ? __('Impayés ').of_period($selected) : __('Total des impayés'), 'value' => $selected,
        ];
    }

    private function dateBounds(string $from, string $to): array
    {
        return [$from.'-01', Carbon::createFromFormat('!Y-m', $to)->endOfMonth()->toDateString()];
    }

    // Facturé et volume par mois de facture, encaissé par date de paiement.
    private function totalsFor(string $from, string $to): array
    {
        $readings = Reading::real()->whereBetween('period', [$from, $to])
            ->selectRaw('COALESCE(SUM(amount), 0) as billed, COALESCE(SUM(consumption), 0) as volume')->first();

        return [
            'billed' => (float) $readings->billed,
            'volume' => (float) $readings->volume,
            'collected' => (float) Payment::whereBetween('paid_at', $this->dateBounds($from, $to))->sum('amount'),
        ];
    }

    // Années proposées : de la plus ancienne facture à l'année en cours.
    private function years(): array
    {
        $first = Reading::min('period');

        return range(now()->year, $first ? min((int) substr($first, 0, 4), now()->year) : now()->year);
    }

    // Verdict d'un pourcentage selon les seuils de config/dashboard.php.
    private function health(float $percent): array
    {
        if ($percent >= config('dashboard.health_good_percent')) {
            return ['level' => 'success', 'label' => __('Bon')];
        }

        return $percent >= config('dashboard.health_warning_percent')
            ? ['level' => 'warning', 'label' => __('À surveiller')]
            : ['level' => 'danger', 'label' => __('Critique')];
    }

    private function recoveryRing(float $billed, float $paid): array
    {
        $rate = $billed > 0 ? $paid / $billed * 100 : null;
        $status = $rate === null ? null : $this->health($rate);
        $rest = max(0, $billed - $paid);

        return [
            'title' => __('Recouvrement'),
            'center' => $rate === null ? '—' : round($rate).' %',
            'sub' => __('du facturé payé'),
            'status' => $status,
            'segments' => [
                ['label' => __('Payé'), 'value' => money($paid), 'share' => $rate ?? 0, 'tone' => $status['level'] ?? 'primary'],
                ['label' => __('Reste à encaisser'), 'value' => money($rest), 'share' => $rate === null ? 0 : 100 - $rate, 'tone' => 'track'],
            ],
            'note' => $rate === null
                ? __('Aucune facture pour le moment.')
                : ($rest > 0 ? __('Il reste ').money($rest).__(' à encaisser.') : __('Tout le facturé est encaissé.')),
        ];
    }

    private function customersRing(int $count, int $inGoodStanding): array
    {
        $share = $count > 0 ? $inGoodStanding / $count * 100 : null;
        $status = $share === null ? null : $this->health($share);
        $behind = $count - $inGoodStanding;

        return [
            'title' => __('Clients en règle'),
            'center' => $count > 0 ? $inGoodStanding.' / '.$count : '—',
            'sub' => __('sans impayé'),
            'status' => $status,
            'segments' => [
                ['label' => __('En règle'), 'value' => (string) $inGoodStanding, 'share' => $share ?? 0, 'tone' => $status['level'] ?? 'primary'],
                ['label' => __('Avec un impayé'), 'value' => (string) $behind, 'share' => $share === null ? 0 : 100 - $share, 'tone' => 'track'],
            ],
            'note' => match (true) {
                $count === 0 => __('Aucun client pour le moment.'),
                $behind === 0 => __('Tous vos clients sont à jour.'),
                default => $behind.__(' client(s) à relancer.'),
            },
        ];
    }

    private function agingRing(Collection $aging, float $total, float $overdue): array
    {
        $old = $aging->filter(fn ($bucket) => $bucket['late_from'] > 60)->sum('share');

        return [
            'title' => __('Âge des impayés'),
            'center' => round($overdue / $total * 100).' %',
            'sub' => __('en retard'),
            'status' => null,
            'segments' => $aging->values()->map(fn ($bucket, $index) => [
                'label' => $bucket['label'], 'value' => money($bucket['amount']), 'share' => $bucket['share'], 'tone' => 'age-'.$index,
            ])->filter(fn ($segment) => $segment['share'] > 0)->values()->all(),
            'note' => $old > 0
                ? round($old).__(' % des impayés ont plus de 60 jours de retard : à relancer en priorité.')
                : __('Aucun impayé de plus de 60 jours de retard.'),
        ];
    }

    private function zonesRing(Collection $zones): array
    {
        $unpaidZones = $zones->filter(fn ($zone) => $zone['amount'] > 0)->values();
        $limit = (int) config('dashboard.ring_zones');
        $others = $unpaidZones->slice($limit);
        $top = $unpaidZones->first();

        // La couleur suit le nom de la zone, pas son rang : un classement qui change ne repeint pas tout.
        $tones = $unpaidZones->take($limit)->pluck('name')->sort()->values()->flip();

        $segments = $unpaidZones->take($limit)->map(fn ($zone) => [
            'label' => $zone['name'], 'value' => money($zone['amount']), 'share' => $zone['share'], 'tone' => 'zone-'.$tones[$zone['name']],
        ]);

        if ($others->isNotEmpty()) {
            $segments->push(['label' => __('Autres zones'), 'value' => money($others->sum('amount')), 'share' => $others->sum('share'), 'tone' => 'muted']);
        }

        return [
            'title' => __('Impayés par zone'),
            'center' => round($top['share']).' %',
            'sub' => __('première zone'),
            'status' => null,
            'segments' => $segments->all(),
            'note' => $top['name'].__(' regroupe la plus grande part des impayés.'),
        ];
    }

    // Les 12 derniers mois : facturé par mois de facture, encaissé par date de paiement.
    private function months(?Carbon $from = null): Collection
    {
        $start = $from ?? now()->startOfMonth()->subMonths(11);

        $billed = Reading::real()->where('period', '>=', $start->format('Y-m'))
            ->selectRaw('period, SUM(amount) as billed, SUM(paid_amount) as paid, SUM(consumption) as volume')
            ->groupBy('period')->get()->keyBy('period');

        $collected = Payment::where('paid_at', '>=', $start->toDateString())->get(['paid_at', 'amount'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at->format('Y-m'))
            ->map(fn ($payments) => (float) $payments->sum('amount'));

        return collect(range(0, 11))->map(function ($offset) use ($start, $billed, $collected) {
            $date = $start->copy()->addMonths($offset);
            $key = $date->format('Y-m');
            $row = $billed[$key] ?? null;

            return [
                'period' => $key,
                'short' => $date->copy()->locale(app()->getLocale())->translatedFormat('M'),
                'billed' => (float) ($row->billed ?? 0),
                'paid' => (float) ($row->paid ?? 0),
                'volume' => (float) ($row->volume ?? 0),
                'collected' => (float) ($collected[$key] ?? 0),
            ];
        });
    }

    // Retire les mois vides du début, en gardant au moins six mois à l'écran.
    private function visibleMonths(Collection $months): Collection
    {
        $first = $months->search(fn ($month) => $month['billed'] > 0 || $month['collected'] > 0);

        return $months->slice($first === false ? 6 : min($first, 6))->values();
    }

    private function ticks(float $max): array
    {
        if ($max <= 0) {
            return [0];
        }

        $magnitude = 10 ** floor(log10($max));
        $step = collect([0.2, 0.5, 1, 2, 5])->map(fn ($factor) => $factor * $magnitude)
            ->first(fn ($candidate) => $max / $candidate <= 4) ?? 10 * $magnitude;

        return range(0, ceil($max / $step) * $step, $step);
    }

    private function aging(Collection $unpaid, float $total): Collection
    {
        $buckets = [
            ['label' => __('Pas encore échu'), 'min' => PHP_INT_MIN, 'max' => 0],
            ['label' => __('1 à 30 jours'), 'min' => 1, 'max' => 30],
            ['label' => __('31 à 60 jours'), 'min' => 31, 'max' => 60],
            ['label' => __('61 à 90 jours'), 'min' => 61, 'max' => 90],
            ['label' => __('Plus de 90 jours'), 'min' => 91, 'max' => PHP_INT_MAX],
        ];

        return collect($buckets)->map(function ($bucket) use ($unpaid, $total) {
            $readings = $unpaid->filter(function (Reading $reading) use ($bucket) {
                $days = $reading->due_date ? (int) $reading->due_date->diffInDays(today(), false) : 0;

                return $days >= $bucket['min'] && $days <= $bucket['max'];
            });
            $amount = round($readings->sum(fn (Reading $reading) => $reading->balance()), 3);

            return [
                'label' => $bucket['label'],
                'late_from' => $bucket['min'],
                'count' => $readings->count(),
                'amount' => $amount,
                'share' => $total > 0 ? $amount / $total * 100 : 0,
            ];
        });
    }

    private function debtors(Collection $unpaid, float $total): Collection
    {
        return $unpaid->groupBy('customer_id')->map(function ($readings) use ($total) {
            $amount = round($readings->sum(fn (Reading $reading) => $reading->balance()), 3);
            $oldest = $readings->pluck('due_date')->filter()->min();

            return (object) [
                'customer' => $readings->first()->customer,
                'amount' => $amount,
                'share' => $total > 0 ? $amount / $total * 100 : 0,
                'invoices' => $readings->count(),
                'days_late' => $oldest ? max(0, (int) $oldest->diffInDays(today(), false)) : 0,
            ];
        })->sortByDesc('amount')->values();
    }

    private function zones(Collection $unpaid, float $total, string $from, string $to): Collection
    {
        $meters = Meter::get(['id', 'zone_id']);
        $volumes = Reading::real()->whereBetween('period', [$from, $to])->get(['meter_id', 'consumption']);
        $zoneOf = $meters->pluck('zone_id', 'id');
        $names = Zone::pluck('name', 'id');

        if ($names->isEmpty()) {
            return collect();
        }

        return $names->put(0, __('Sans zone'))->map(function ($name, $id) use ($meters, $unpaid, $volumes, $zoneOf, $total) {
            $amount = round($unpaid->filter(fn (Reading $reading) => (int) $reading->meter->zone_id === $id)
                ->sum(fn (Reading $reading) => $reading->balance()), 3);

            return [
                'name' => $name,
                'meters' => $meters->filter(fn ($meter) => (int) $meter->zone_id === $id)->count(),
                'volume' => (float) $volumes->filter(fn ($row) => (int) ($zoneOf[$row->meter_id] ?? 0) === $id)->sum('consumption'),
                'amount' => $amount,
                'share' => $total > 0 ? $amount / $total * 100 : 0,
            ];
        })->filter(fn ($zone) => $zone['meters'] > 0)->sortByDesc('amount')->values();
    }

    // Compare le dernier relevé de chaque compteur à sa moyenne : une alerte invite à vérifier, elle ne prouve rien.
    private function anomalies(string $previous): Collection
    {
        $since = now()->startOfMonth()->subMonths((int) config('dashboard.consumption_history_months'))->format('Y-m');
        $factor = (float) config('dashboard.consumption_spike_factor');

        return Reading::real()->with(['meter', 'customer'])->where('period', '>=', $since)->orderBy('period')->get()
            ->groupBy('meter_id')
            ->map(function ($readings) use ($previous, $factor) {
                $latest = $readings->last();
                $history = $readings->slice(0, -1);
                $average = $history->avg(fn (Reading $reading) => (float) $reading->consumption);
                $consumption = (float) $latest->consumption;

                if ($latest->period < $previous || $history->isEmpty() || $average <= 0) {
                    return null;
                }

                $problem = match (true) {
                    $consumption == 0.0 => __('Consommation nulle : compteur bloqué ou absence ?'),
                    $history->count() >= 2 && $consumption >= $factor * $average => __('Forte hausse : fuite ou erreur de relevé ?'),
                    default => null,
                };

                return $problem ? (object) [
                    'reading' => $latest,
                    'consumption' => $consumption,
                    'average' => $average,
                    'problem' => $problem,
                ] : null;
            })->filter()->values();
    }

    private function actions(int $pendingCount, Collection $dueSoon, int $anomalyCount, string $period): array
    {
        [$toCut, $withoutNotice] = $this->noticeCounts();
        $actions = [];

        if ($toCut > 0) {
            $actions[] = ['level' => 'critical', 'tag' => __('Urgent'), 'icon' => 'power-off', 'title' => $toCut.__(' compteur(s) à couper'),
                'text' => __('La date limite de l\'avis de coupure est dépassée.'), 'cta' => __('Voir les avis'), 'url' => route('notices.index')];
        }
        if ($withoutNotice > 0) {
            $actions[] = ['level' => 'critical', 'tag' => __('Urgent'), 'icon' => 'circle-alert', 'title' => $withoutNotice.__(' compteur(s) en retard sans avis'),
                'text' => __('Des factures sont échues et aucun avis n\'a été généré.'), 'cta' => __('Générer les avis'), 'url' => route('notices.index')];
        }
        if ($anomalyCount > 0) {
            $actions[] = ['level' => 'warning', 'tag' => __('À vérifier'), 'icon' => 'gauge', 'title' => $anomalyCount.__(' consommation(s) inhabituelle(s)'),
                'text' => __('Possible fuite, compteur bloqué ou erreur de relevé.'), 'cta' => __('Voir le détail'), 'url' => '#anomalies'];
        }
        if ($dueSoon->isNotEmpty()) {
            $actions[] = ['level' => 'warning', 'tag' => __('À surveiller'), 'icon' => 'receipt', 'title' => $dueSoon->count().__(' facture(s) à échéance sous ').config('dashboard.due_soon_days').__(' jours'),
                'text' => money($dueSoon->sum(fn (Reading $reading) => $reading->balance())).__(' à encaisser : une relance est encore possible.'), 'cta' => __('Voir les impayés'), 'url' => route('unpaid.index')];
        }
        if ($pendingCount > 0) {
            $actions[] = ['level' => 'info', 'tag' => __('À faire'), 'icon' => 'clipboard-list', 'title' => $pendingCount.__(' compteur(s) à relever'),
                'text' => __('Pas encore de relevé pour ').period_label($period).'.', 'cta' => __('Saisir les relevés'), 'url' => route('readings.index', ['period' => $period])];
        }

        $unattached = Meter::where('is_active', true)->whereNull('customer_id')->count();
        if ($unattached > 0) {
            $actions[] = ['level' => 'info', 'tag' => __('À faire'), 'icon' => 'users', 'title' => $unattached.__(' compteur(s) sans client'),
                'text' => __('Ces compteurs ne peuvent pas être facturés.'), 'cta' => __('Voir les compteurs'), 'url' => route('meters.index')];
        }

        return $actions;
    }

    /** @return array{0: int, 1: int} compteurs à couper, compteurs en retard sans avis */
    private function noticeCounts(): array
    {
        $groups = Reading::overdue()
            ->selectRaw('meter_id, customer_id, MIN(due_date) as oldest_due')
            ->groupBy('meter_id', 'customer_id')->get();

        $notices = CutoffNotice::whereIn('meter_id', $groups->pluck('meter_id'))->orderBy('id')->get()
            ->keyBy(fn ($notice) => $notice->meter_id.'-'.$notice->customer_id);

        $toCut = $withoutNotice = 0;
        foreach ($groups as $group) {
            $notice = $notices[$group->meter_id.'-'.$group->customer_id] ?? null;

            if (! $notice || substr((string) $group->oldest_due, 0, 10) > $notice->issued_at->toDateString()) {
                $withoutNotice++;
            } elseif ($notice->deadline->lt(today())) {
                $toCut++;
            }
        }

        return [$toCut, $withoutNotice];
    }

    private function insights(Collection $debtors, Collection $aging, Collection $zones, Collection $months, Collection $dueSoon, string $previous): array
    {
        $insights = [];
        $alert = (float) config('dashboard.concentration_alert_percent');

        $top = $debtors->first();
        if ($top && $debtors->count() >= 2 && $top->share >= $alert) {
            $insights[] = $top->customer->name.__(' concentre ').round($top->share).__(' % des impayés (').money($top->amount).').';
        }

        $old = $aging->where('late_from', '>=', 61);
        if ($old->sum('amount') > 0) {
            $insights[] = round($old->sum('share')).__(' % des impayés (').money($old->sum('amount')).__(') ont plus de 60 jours de retard : ce sont les plus difficiles à recouvrer.');
        }

        $debtZones = $zones->where('amount', '>', 0);
        if ($debtZones->count() >= 2 && $debtZones->first()['share'] >= $alert) {
            $insights[] = $debtZones->first()['name'].__(' regroupe ').round($debtZones->first()['share']).__(' % des impayés.');
        }

        // Compare les deux derniers mois terminés : le mois en cours n'a pas encore eu le temps d'être payé.
        $index = $months->search(fn ($month) => $month['period'] === $previous);
        $last = $months[$index];
        $before = $months[$index - 1];
        if ($last['billed'] > 0 && $before['billed'] > 0) {
            $rate = fn ($month) => round($month['paid'] / $month['billed'] * 100);
            $insights[] = __('À ce jour, ').$rate($last).__(' % des factures ').of_period($last['period']).__(' sont payées, contre ').$rate($before).__(' % pour ').period_label($before['period']).'.';
        }

        if ($dueSoon->isNotEmpty()) {
            $insights[] = money($dueSoon->sum(fn (Reading $reading) => $reading->balance())).__(' arrivent à échéance dans les ').config('dashboard.due_soon_days').__(' prochains jours.');
        }

        return $insights;
    }
}
