<?php

namespace App\Models;

use App\Models\Concerns\Lockable;
use App\Support\PlanFeatures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Offre d'abonnement : prix, durée, fonctionnalités et limites (composée dans l'admin). */
class Plan extends Model
{
    use Lockable;

    protected $fillable = ['slug', 'name', 'description', 'price', 'period_days', 'features', 'limits', 'provider_types', 'is_active', 'is_highlighted', 'position'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'provider_types' => 'array',
            'price' => 'integer',
            'period_days' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_highlighted' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('price');
    }

    /** Offre appliquée aux prestataires sans abonnement en cours. */
    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /** Offres que peut choisir un prestataire de ce type. */
    public static function availableFor(Provider $provider): Collection
    {
        return static::where('is_active', true)->ordered()->get()
            ->filter(fn (self $plan) => $plan->appliesTo($provider))->values();
    }

    public function appliesTo(Provider $provider): bool
    {
        return empty($this->provider_types) || in_array($provider->getRawOriginal('type'), $this->provider_types, true);
    }

    public function includes(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    /** null = illimité */
    public function limit(string $key): ?int
    {
        $value = (int) ($this->limits[$key] ?? 0);

        return $value > 0 ? $value : null;
    }

    public function priceLabel(): string
    {
        if ($this->price === 0) {
            return 'Gratuit';
        }

        return number_format($this->price, 0, ',', ' ').' FCFA / '.($this->period_days === 30 ? 'mois' : $this->period_days.' jours');
    }

    /** @return list<string> */
    public function featureLabels(): array
    {
        return collect($this->features)->map(fn ($f) => PlanFeatures::label($f))->values()->all();
    }
}
