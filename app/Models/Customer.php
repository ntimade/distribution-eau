<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['name', 'gender', 'phone', 'email', 'address', 'price_per_m3'];

    // Civilité des messages : « Monsieur/Madame » tant que le sexe n'est pas renseigné.
    public function title(): string
    {
        return match ($this->gender) {
            'M' => __('Monsieur'),
            'F' => __('Madame'),
            default => __('Monsieur/Madame'),
        };
    }

    // Pour les SMS : la civilité abrégée évite de payer un deuxième SMS.
    public function smsName(): string
    {
        $title = match ($this->gender) {
            'M' => __('M.'),
            'F' => __('Mme'),
            default => __('M./Mme'),
        };

        return $title.' '.$this->name;
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }
}
