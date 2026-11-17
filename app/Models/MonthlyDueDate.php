<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

class MonthlyDueDate extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['period', 'due_date'];

    protected $casts = ['due_date' => 'date'];
}
