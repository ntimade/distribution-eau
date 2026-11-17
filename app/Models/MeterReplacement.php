<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeterReplacement extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'old_meter_id', 'new_meter_id', 'customer_id', 'user_id', 'replaced_on',
        'old_previous_index', 'old_final_index', 'old_consumption', 'notes',
    ];

    protected $casts = ['replaced_on' => 'date'];

    public function oldMeter(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'old_meter_id');
    }

    public function newMeter(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'new_meter_id');
    }

    // La facture qui regroupe l'ancien et le nouveau compteur, une fois le relevé saisi.
    public function reading(): HasOne
    {
        return $this->hasOne(Reading::class);
    }
}
