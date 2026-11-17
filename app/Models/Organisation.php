<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Organisation extends Model
{
    protected $fillable = ['name', 'code', 'phone', 'phone_country', 'address', 'logo_path', 'currency', 'price_per_m3', 'payment_due_days', 'cutoff_grace_days'];

    protected $casts = ['suspended_at' => 'datetime', 'subscribed_until' => 'date', 'trial_ends_at' => 'date'];

    public function subscriptionState(): string
    {
        if (! $this->subscribed_until) {
            if (! $this->trial_ends_at) {
                return 'none';
            }

            return $this->trial_ends_at->gte(today()) ? 'trial' : 'trial_over';
        }

        if ($this->subscribed_until->lt(today())) {
            return 'expired';
        }

        return $this->subscribed_until->lte(today()->addDays((int) config('subscription.reminder_days'))) ? 'ending' : 'active';
    }

    // Un plan choisi, même en attente du paiement, garde l'accès ouvert. Sans plan proposé, rien ne peut être choisi.
    public function isTrialLocked(): bool
    {
        return $this->subscriptionState() === 'trial_over'
            && ! SubscriptionPayment::where('organisation_id', $this->id)->where('status', SubscriptionPayment::PENDING)->exists()
            && SubscriptionPlan::available()->exists();
    }

    // Abonnement expiré : seule une validation du paiement rouvre l'accès, pas une simple demande en attente.
    public function isAccessLocked(): bool
    {
        return $this->subscriptionState() === 'expired' || $this->isTrialLocked();
    }

    public function subscriptionLabel(): string
    {
        return match ($this->subscriptionState()) {
            'active' => __('Abonnement actif'),
            'ending' => __('Bientôt à renouveler'),
            'trial' => __('Essai gratuit'),
            'trial_over' => __('Essai terminé'),
            'expired' => __('Abonnement expiré'),
            default => __('Sans abonnement'),
        };
    }

    public function subscriptionBadge(): string
    {
        return match ($this->subscriptionState()) {
            'active' => 'ok',
            'ending', 'trial' => 'partial',
            'expired', 'trial_over' => 'ko',
            default => 'off',
        };
    }

    // Logo intégré à la page publique d'une facture, où la route protégée n'est pas accessible.
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! Storage::disk('local')->exists($this->logo_path)) {
            return null;
        }

        return 'data:'.Storage::disk('local')->mimeType($this->logo_path).';base64,'.base64_encode(Storage::disk('local')->get($this->logo_path));
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function currencyDecimals(): int
    {
        return config("currencies.{$this->currency}.1", 2);
    }

    // Même format que NEY Santé : initiales du nom + numéro (« Eau du Nord » => EDN-001).
    public static function generateCode(string $name): string
    {
        $words = array_values(array_filter(explode(' ', preg_replace('/[^a-zA-Z\s]/', ' ', Str::ascii($name)))));

        $prefix = '';
        foreach (array_slice($words, 0, 3) as $word) {
            $prefix .= strtoupper($word[0]);
        }
        $prefix = str_pad($prefix, 3, 'X');

        $counter = static::where('code', 'like', $prefix.'-%')->count() + 1;
        do {
            $code = $prefix.'-'.str_pad($counter++, 3, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
