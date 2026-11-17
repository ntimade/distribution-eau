<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meter extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'customer_id', 'zone_id', 'number', 'initial_index', 'installed_at',
        'latitude', 'longitude', 'is_active',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    // Le remplacement de ce compteur par un autre.
    public function replacement(): HasOne
    {
        return $this->hasOne(MeterReplacement::class, 'old_meter_id');
    }

    // Le remplacement par lequel ce compteur a été posé.
    public function replacedFrom(): HasOne
    {
        return $this->hasOne(MeterReplacement::class, 'new_meter_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    // Ajoute les impayés du client actuel : la dette d'un ancien client reste à
    // son nom et ne met pas en défaut celui qui a repris le compteur.
    public function scopeWithPaymentStatus(Builder $query): Builder
    {
        $unpaid = fn () => Reading::whereColumn('readings.meter_id', 'meters.id')
            ->whereColumn('readings.customer_id', 'meters.customer_id')
            ->whereColumn('paid_amount', '<', 'amount');

        return $query->addSelect([
            'unpaid' => $unpaid()->selectRaw('COALESCE(SUM(amount - paid_amount), 0)'),
            'unpaid_months' => $unpaid()->selectRaw('COUNT(*)'),
        ]);
    }

    public function paymentStatus(): string
    {
        if (! $this->customer_id) {
            return 'none';
        }

        return (float) $this->unpaid > 0 ? 'late' : 'ok';
    }

    public function latestReading(): ?Reading
    {
        return $this->readings()->real()->orderByDesc('period')->first();
    }

    // Dernier mois de dette repris d'un ancien système : les relevés commencent après.
    public function lastOpeningPeriod(): ?string
    {
        return $this->readings()->where('is_opening', true)->max('period');
    }

    // Index de départ du prochain relevé : celui du dernier mois relevé, sinon l'index de pose.
    public function lastIndex(): float
    {
        return (float) ($this->latestReading()?->current_index ?? $this->initial_index);
    }

    // Un releveur limité à des zones ne voit que les données de ces zones.
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $user->isLimitedToZones() ? $query->whereIn('meters.zone_id', $user->zoneIds()) : $query;
    }
}
