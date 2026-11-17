<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Un encaissement : plusieurs mois réglés en une seule fois, choisis librement par le client, sur un ou
 * plusieurs de ses compteurs. Chaque mois a son propre paiement ; l'encaissement en est le reçu.
 */
class Receipt extends Model
{
    use BelongsToOrganisation, SoftDeletes;

    protected $fillable = ['customer_id', 'user_id', 'total', 'paid_at', 'method', 'reference'];

    protected $casts = ['paid_at' => 'date', 'whatsapp_opened_at' => 'datetime', 'sms_opened_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // « REC-2026-0012 » : le numéro du reçu.
    public function number(): string
    {
        return 'REC-'.$this->paid_at->format('Y').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Les mois réglés, compteur par compteur : chaque compteur reste séparé sur le reçu.
     * Chaque groupe : le compteur, ses paiements (avec leur facture) et le sous-total.
     */
    public function byMeter(): SupportCollection
    {
        return $this->payments
            ->sortBy(fn (Payment $payment) => [$payment->reading->meter->number, $payment->reading->period])
            ->groupBy(fn (Payment $payment) => $payment->reading->meter_id)
            ->map(fn (SupportCollection $payments) => (object) [
                'meter' => $payments->first()->reading->meter,
                'payments' => $payments->values(),
                'subtotal' => round((float) $payments->sum('amount'), 3),
                // Ce que ce client doit encore sur ce compteur, après cet encaissement.
                'remaining' => round((float) Reading::unpaid()
                    ->where('meter_id', $payments->first()->reading->meter_id)
                    ->where('customer_id', $this->customer_id)
                    ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')
                    ->value('total'), 3),
            ])
            ->values();
    }
}
