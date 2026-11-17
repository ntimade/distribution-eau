<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reading extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'meter_id', 'meter_replacement_id', 'carried_consumption', 'customer_id', 'user_id', 'period', 'is_opening', 'read_at', 'due_date', 'previous_index',
        'current_index', 'consumption', 'unit_price', 'maintenance_fee', 'amount', 'paid_amount', 'notes',
    ];

    protected $casts = ['read_at' => 'date', 'due_date' => 'date', 'is_opening' => 'boolean', 'whatsapp_opened_at' => 'datetime', 'sms_opened_at' => 'datetime', 'reminder_opened_at' => 'datetime'];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(MeterReplacement::class, 'meter_replacement_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Les vrais relevés, sans les soldes de départ repris d'un ancien système.
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_opening', false);
    }

    // Un relevé par compteur et par mois : ce numéro est donc unique dans l'organisation.
    public function invoiceNumber(): string
    {
        return ($this->is_opening ? 'SOLDE-' : 'FAC-').$this->period.'-'.$this->meter->number;
    }

    public function label(): string
    {
        return $this->is_opening
            ? __('Solde de départ (').period_label($this->period).')'
            : period_label($this->period);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereColumn('paid_amount', '<', 'amount');
    }

    // En retard à partir du lendemain de l'échéance.
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->where('due_date', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->balance() > 0 && $this->due_date?->lt(today());
    }

    public function balance(): float
    {
        return round((float) $this->amount - (float) $this->paid_amount, 3);
    }

    // Tout relevé se corrige ; le solde de départ se modifie depuis la fiche du compteur.
    public function isEditable(): bool
    {
        return ! $this->is_opening;
    }

    // Supprimer un relevé intermédiaire casserait l'enchaînement des index.
    public function isDeletable(): bool
    {
        return ! $this->is_opening
            && ! $this->payments()->exists()
            && ! $this->meter_replacement_id
            && ! $this->meter->replacement()->exists()
            && $this->meter->latestReading()?->id === $this->id;
    }

    // Un releveur limité à des zones ne voit que les données de ces zones.
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $user->isLimitedToZones() ? $query->whereHas('meter', fn (Builder $meter) => $meter->whereIn('meters.zone_id', $user->zoneIds())) : $query;
    }
}
