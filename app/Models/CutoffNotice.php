<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CutoffNotice extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['meter_id', 'customer_id', 'user_id', 'issued_at', 'deadline'];

    protected $casts = ['issued_at' => 'date', 'deadline' => 'date', 'message_opened_at' => 'datetime'];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function number(): string
    {
        return 'AC-'.$this->issued_at->format('Ym').'-'.$this->id;
    }

    // Les factures encore dues par ce client sur ce compteur et échues à ce jour.
    public function overdueReadings(): Builder
    {
        return Reading::overdue()
            ->where('meter_id', $this->meter_id)
            ->where('customer_id', $this->customer_id)
            ->orderBy('period');
    }
}
