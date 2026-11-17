<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CutoffNotice;
use App\Models\Meter;
use App\Models\Organisation;
use App\Models\Reading;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CutoffNoticeService
{
    /**
     * Une ligne par compteur et par client ayant des factures échues. Un avis reste valable
     * tant qu'une dette antérieure à son émission est impayée ; une fois réglée, un
     * nouveau retard demande un nouvel avis.
     */
    public function rows(): Collection
    {
        $groups = Reading::overdue()
            ->selectRaw('meter_id, customer_id, COUNT(*) as invoices, SUM(amount - paid_amount) as total, MIN(due_date) as oldest_due')
            ->groupBy('meter_id', 'customer_id')
            ->get();

        $meters = Meter::whereIn('id', $groups->pluck('meter_id'))->get()->keyBy('id');
        $customers = Customer::whereIn('id', $groups->pluck('customer_id'))->get()->keyBy('id');
        $notices = CutoffNotice::whereIn('meter_id', $groups->pluck('meter_id'))->orderBy('id')->get()
            ->keyBy(fn ($notice) => $notice->meter_id.'-'.$notice->customer_id);

        return $groups->map(function ($group) use ($meters, $customers, $notices) {
            $notice = $notices[$group->meter_id.'-'.$group->customer_id] ?? null;

            if ($notice && substr((string) $group->oldest_due, 0, 10) > $notice->issued_at->toDateString()) {
                $notice = null;
            }

            return (object) [
                'meter' => $meters[$group->meter_id],
                'customer' => $customers[$group->customer_id],
                'invoices' => (int) $group->invoices,
                'total' => (float) $group->total,
                'oldest_due' => substr((string) $group->oldest_due, 0, 10),
                'notice' => $notice,
                'status' => ! $notice ? 'none' : ($notice->deadline->lt(today()) ? 'cut' : 'sent'),
            ];
        })->sortBy(fn ($row) => [['cut' => 0, 'none' => 1, 'sent' => 2][$row->status], -$row->total])->values();
    }

    // Les avis qu'il reste à envoyer : le client a son numéro et n'a pas encore reçu de message.
    public function toSend(): Collection
    {
        return $this->rows()
            ->filter(fn ($row) => str_starts_with((string) $row->customer->phone, '+') && ! $row->notice?->message_opened_at)
            ->values();
    }

    // Crée les avis manquants et rend tous ceux des lignes demandées.
    public function issue(Collection $rows): Collection
    {
        return DB::transaction(function () use ($rows) {
            $organisation = Organisation::whereKey(Auth::user()->organisation_id)->lockForUpdate()->first();

            return $rows->map(fn ($row) => $row->notice ?? CutoffNotice::create([
                'meter_id' => $row->meter->id,
                'customer_id' => $row->customer->id,
                'user_id' => Auth::id(),
                'issued_at' => now()->toDateString(),
                'deadline' => now()->addDays($organisation->cutoff_grace_days)->toDateString(),
            ]));
        });
    }
}
