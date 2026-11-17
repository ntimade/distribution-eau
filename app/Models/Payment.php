<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToOrganisation, SoftDeletes;

    protected $fillable = ['reading_id', 'receipt_id', 'user_id', 'amount', 'paid_at', 'method', 'reference'];

    protected $casts = ['paid_at' => 'date', 'whatsapp_opened_at' => 'datetime', 'sms_opened_at' => 'datetime'];

    // L'encaissement de plusieurs mois dont ce paiement fait partie, s'il y en a un.
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function reading(): BelongsTo
    {
        return $this->belongsTo(Reading::class);
    }
}
