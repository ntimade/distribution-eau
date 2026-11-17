<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_READER = 'reader';

    protected $fillable = ['organisation_id', 'name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'all_zones' => 'boolean',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function isReader(): bool
    {
        return $this->role === self::ROLE_READER;
    }

    // Les zones d'un releveur, sauf s'il a accès à toutes (all_zones).
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'reader_zone');
    }

    public function zonesLabel(): string
    {
        return $this->all_zones ? __('Toutes les zones') : ($this->zones->pluck('name')->sort()->implode(', ') ?: '—');
    }

    // Requête des identifiants de zones du releveur.
    public function zoneIds(): QueryBuilder
    {
        return DB::table('reader_zone')->where('user_id', $this->id)->select('zone_id');
    }

    public function isLimitedToZones(): bool
    {
        return $this->isReader() && ! $this->all_zones;
    }

    public function securityAnswers(): HasMany
    {
        return $this->hasMany(SecurityAnswer::class);
    }
}
