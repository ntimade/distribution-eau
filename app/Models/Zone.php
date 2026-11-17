<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['name', 'description'];

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }
}
