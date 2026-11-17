<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

class MaintenanceFee extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['period', 'amount'];
}
