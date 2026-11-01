<?php

namespace App\Models;

use App\Casts\ProviderTypeCast;
use App\Enums\Availability;
use App\Enums\ProviderStatus;
use App\Models\Concerns\Lockable;
use App\Models\Profiles\GarageProfile;
use App\Models\Profiles\MechanicProfile;
use App\Models\Profiles\ShopProfile;
use App\Models\Profiles\StationProfile;
use App\Models\Profiles\TowTruckProfile;
use App\Models\Profiles\TypeProfile;
use App\Support\Phone;
use App\Support\ProviderProfiles;
use Carbon\CarbonInterface;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    use Lockable;

    /**
     * Le statut de validation, la note et l'approbation ne sont pas assignables :
     * un prestataire ne peut pas se valider lui-même via un formulaire.
     * Idem pour la mise en avant (is_featured) et le suivi des changements (needs_review).
     */
    protected $fillable = [
        'type', 'business_name', 'manager_name', 'description', 'phone', 'whatsapp',
        'address', 'city_id', 'neighborhood_id', 'latitude', 'longitude',
        'is_formal', 'rccm', 'ifu', 'night_service',
        'delivery_available', 'delivery_details', 'payment_methods',
    ];

    /** Champs dont la modification, une fois la fiche validée, doit être revue par l'admin. */
    public const SENSITIVE_FIELDS = ['business_name', 'type', 'phone', 'whatsapp', 'latitude', 'longitude', 'rccm'];

    protected $hidden = ['location'];

    protected function casts(): array
    {
        return [
            'type' => ProviderTypeCast::class,
            'status' => ProviderStatus::class,
            'availability' => Availability::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'is_formal' => 'boolean',
            'night_service' => 'boolean',
            'approved_at' => 'datetime',
            'rating_avg' => 'float',
            'is_featured' => 'boolean',
            'needs_review' => 'boolean',
            'pending_changes' => 'array',
            'delivery_available' => 'boolean',
            'payment_methods' => 'array',
            'custom_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Provider $provider) {
            $provider->slug ??= static::uniqueSlug($provider->business_name);
            $provider->status ??= ProviderStatus::Pending;
            $provider->availability ??= Availability::Available;
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'prestataire';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relations ------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class)->orderBy('day');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProviderPhoto::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Employés de la boutique (gérant, vendeur, caissier). Le propriétaire est user(). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_members')->withPivot('role')->withTimestamps();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest();
    }

    // Fiches métier (une table par catégorie) ----------------------------------

    public function mechanicProfile(): HasOne
    {
        return $this->hasOne(MechanicProfile::class);
    }

    public function garageProfile(): HasOne
    {
        return $this->hasOne(GarageProfile::class);
    }

    public function towTruckProfile(): HasOne
    {
        return $this->hasOne(TowTruckProfile::class);
    }

    public function stationProfile(): HasOne
    {
        return $this->hasOne(StationProfile::class);
    }

    public function shopProfile(): HasOne
    {
        return $this->hasOne(ShopProfile::class);
    }

    /** Catégorie de fiche métier du type actuel (mechanic, garage, tow, station, shop) ou null. */
    public function profileKind(): ?string
    {
        return $this->type?->profileKind();
    }

    /** Fiche métier du type actuel (instance vide non enregistrée si elle n'existe pas encore). */
    public function details(): ?TypeProfile
    {
        return $this->detailsFor($this->profileKind());
    }

    /** Fiche métier d'une catégorie donnée (existante ou vide) : sert aux formulaires qui changent selon le type choisi. */
    public function detailsFor(?string $kind): ?TypeProfile
    {
        $definition = ProviderProfiles::get($kind);
        if (! $definition) {
            return null;
        }

        return $this->{$definition['relation']} ?? new $definition['model'];
    }

    public function saveDetails(array $input): void
    {
        $kind = $this->profileKind();
        if (! ProviderProfiles::get($kind)) {
            return;
        }

        $this->{ProviderProfiles::get($kind)['relation']}()->updateOrCreate([], ProviderProfiles::values($kind, $input));
        $this->unsetRelation(ProviderProfiles::get($kind)['relation']);
    }

    // Abonnement -----------------------------------------------------------------

    public function currentSubscription(): ?Subscription
    {
        if (! $this->relationLoaded('currentSubscriptionRel')) {
            $this->setRelation('currentSubscriptionRel', $this->subscriptions()->current()->with('plan')->first());
        }

        return $this->getRelation('currentSubscriptionRel');
    }

    /** Offre en cours, ou l'offre par défaut (gratuite) sans abonnement actif. */
    public function currentPlan(): ?Plan
    {
        return $this->currentSubscription()?->plan ?? Plan::default();
    }

    /**
     * Accès à une fonctionnalité de l'offre. Abonnements désactivés dans l'admin :
     * tout est accessible gratuitement.
     */
    public function hasPlanFeature(string $feature): bool
    {
        if (! feature('subscriptions_enabled')) {
            return true;
        }

        return (bool) $this->currentPlan()?->includes($feature);
    }

    /** Limite de l'offre (null = illimité). */
    public function planLimit(string $key): ?int
    {
        return feature('subscriptions_enabled') ? $this->currentPlan()?->limit($key) : null;
    }

    /** Applique les avantages automatiques de l'offre en cours (mise à la une). */
    public function syncPlanBenefits(): void
    {
        $this->unsetRelation('currentSubscriptionRel');
        $plan = $this->currentPlan();
        $featured = (bool) $plan?->includes('featured');

        if ($featured && ! $this->is_featured) {
            $this->forceFill(['is_featured' => true])->saveQuietly();
        } elseif (! $featured && $this->is_featured && $this->subscriptions()->whereHas('plan', fn ($q) => $q->whereJsonContains('features', 'featured'))->exists()) {
            // Mise à la une obtenue par un abonnement désormais terminé.
            $this->forceFill(['is_featured' => false])->saveQuietly();
        }
    }

    /** Vendeurs de pièces et garages (catalogue facultatif) peuvent vendre sur le marketplace. */
    public function canSellParts(): bool
    {
        return $this->type->canSellParts();
    }

    /** Module activé pour le métier de ce prestataire (voir App\Support\TradeModules). */
    public function hasModule(string $module): bool
    {
        return (bool) $this->type?->hasModule($module);
    }

    // Visibilité publique -----------------------------------------------------

    /**
     * Seuls les prestataires validés dont le compte est actif apparaissent au public.
     * Toute requête publique DOIT passer par ce scope.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('providers.status', ProviderStatus::Approved)
            ->whereHas('user', fn (Builder $q) => $q->where('is_active', true));
    }

    public function isVisible(): bool
    {
        return $this->status === ProviderStatus::Approved && $this->user?->is_active;
    }

    /**
     * Ajoute `distance_m` (mètres) et limite au rayon donné, via l'index PostGIS.
     */
    public function scopeNear(Builder $query, float $lat, float $lng, ?float $radiusKm = null): void
    {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        if ($query->getQuery()->columns === null) {
            $query->select('providers.*');
        }

        $query->selectRaw("ST_Distance(providers.location, {$point}) AS distance_m", [$lng, $lat])
            ->whereNotNull('providers.location');

        if ($radiusKm !== null) {
            $query->whereRaw("ST_DWithin(providers.location, {$point}, ?)", [$lng, $lat, $radiusKm * 1000]);
        }
    }

    /**
     * Ouvert à l'instant donné d'après les horaires (gère les plages qui passent minuit
     * et les plages 24h/24 où ouverture = fermeture).
     */
    public function scopeOpenAt(Builder $query, CarbonInterface $at): void
    {
        $time = $at->format('H:i:s');
        $today = $at->dayOfWeekIso;
        $yesterday = $at->copy()->subDay()->dayOfWeekIso;

        $query->whereHas('schedules', function (Builder $q) use ($time, $today, $yesterday) {
            $q->where(function (Builder $q) use ($time, $today) {
                $q->where('day', $today)->where(function (Builder $q) use ($time) {
                    $q->whereColumn('opens_at', 'closes_at')
                        ->orWhere(fn ($q) => $q->where('opens_at', '<=', $time)->where('closes_at', '>', $time))
                        ->orWhere(fn ($q) => $q->whereColumn('closes_at', '<', 'opens_at')->where('opens_at', '<=', $time));
                });
            })->orWhere(function (Builder $q) use ($time, $yesterday) {
                $q->where('day', $yesterday)
                    ->whereColumn('closes_at', '<', 'opens_at')
                    ->where('closes_at', '>', $time);
            });
        });
    }

    public function isOpenAt(CarbonInterface $at): bool
    {
        $time = $at->format('H:i:s');
        $today = $at->dayOfWeekIso;
        $yesterday = $at->copy()->subDay()->dayOfWeekIso;

        foreach ($this->schedules as $s) {
            $opens = $s->opens_at;
            $closes = $s->closes_at;
            $overnight = $closes < $opens;

            if ($s->day === $today && ($opens === $closes || ($opens <= $time && ($overnight || $time < $closes)))) {
                return true;
            }
            if ($s->day === $yesterday && $overnight && $time < $closes) {
                return true;
            }
        }

        return false;
    }

    // Présentation -------------------------------------------------------------

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function whatsappUrl(): ?string
    {
        return Phone::whatsappUrl(
            $this->whatsapp ?: $this->phone,
            "Bonjour {$this->business_name}, je vous contacte via MaPanne."
        );
    }

    public function callUrl(): string
    {
        return 'tel:'.$this->phone;
    }

    public function directionsUrl(): ?string
    {
        if ($this->latitude === null) {
            return null;
        }

        return "https://www.openstreetmap.org/directions?route=%3B{$this->latitude}%2C{$this->longitude}";
    }

    public function distanceLabel(): ?string
    {
        $m = $this->getAttribute('distance_m');
        if ($m === null) {
            return null;
        }

        return $m < 1000 ? round($m / 10) * 10 .' m' : number_format($m / 1000, 1, ',', ' ').' km';
    }

    /** @return list<array{label:string, class:string}> */
    public function badges(): array
    {
        $badges = [];

        if ($this->is_featured && feature('featured_enabled')) {
            $badges[] = ['label' => '⭐ À la une', 'class' => 'bg-brand-100 text-brand-800'];
        }
        if ($this->status === ProviderStatus::Approved) {
            $badges[] = ['label' => '✓ Vérifié', 'class' => 'bg-emerald-100 text-emerald-800'];
        }
        if ($this->is_formal) {
            $badges[] = ['label' => 'Entreprise enregistrée', 'class' => 'bg-sky-100 text-sky-800'];
        }
        if ($this->night_service) {
            $badges[] = ['label' => '🌙 Service de nuit', 'class' => 'bg-indigo-100 text-indigo-800'];
        }
        if ($this->reviews_count >= 5 && $this->rating_avg >= 4.5) {
            $badges[] = ['label' => '★ Très bien noté', 'class' => 'bg-amber-100 text-amber-800'];
        }

        return $badges;
    }

    /** Avis visibles du public : ni masqués, ni en attente de modération. */
    public function publishedReviews(): HasMany
    {
        return $this->reviews()->where('is_hidden', false)->where('is_pending', false);
    }

    /**
     * Après modification par le prestataire d'une fiche validée : si un champ sensible a changé,
     * la fiche est signalée à l'admin (et dépubliée si l'option correspondante est activée).
     *
     * @param  array<string, mixed>  $original  valeurs avant modification
     */
    public function flagSensitiveChanges(array $original): bool
    {
        if ($this->status !== ProviderStatus::Approved || ! feature('sensitive_changes_review')) {
            return false;
        }

        $changes = [];
        foreach (self::SENSITIVE_FIELDS as $field) {
            $before = $original[$field] ?? null;
            $after = $this->getRawOriginal($field);
            // Une position qui bouge de moins de ~50 m n'est pas un changement sensible.
            if (in_array($field, ['latitude', 'longitude'], true) && $before !== null && $after !== null
                && abs((float) $before - (float) $after) < 0.0005) {
                continue;
            }
            if ((string) $before !== (string) $after) {
                $changes[$field] = ['avant' => $before, 'après' => $after];
            }
        }

        if (! $changes) {
            return false;
        }

        $this->forceFill([
            'needs_review' => true,
            'pending_changes' => array_merge($this->pending_changes ?? [], $changes),
        ]);
        if (feature('sensitive_changes_unpublish')) {
            $this->status = ProviderStatus::Pending;
        }
        $this->saveQuietly();
        ActivityLog::record('provider.sensitive_changes', $this, $changes);

        return true;
    }

    /** Recalcule la note à partir des seuls avis publiés. */
    public function refreshRating(): void
    {
        $stats = $this->publishedReviews()
            ->selectRaw('COUNT(*) AS n, COALESCE(AVG(rating), 0) AS avg')
            ->first();

        $this->forceFill([
            'reviews_count' => (int) $stats->n,
            'rating_avg' => round((float) $stats->avg, 2),
        ])->saveQuietly();
    }

    public function profileCompletion(): array
    {
        $checks = [
            'Description' => filled($this->description),
            'Position sur la carte' => $this->latitude !== null,
            'Services' => $this->services()->exists(),
            'Horaires' => $this->schedules()->exists(),
            'Logo ou photo' => filled($this->logo_path) || $this->photos()->exists(),
        ];

        return [
            'checks' => $checks,
            'percent' => (int) round(count(array_filter($checks)) / count($checks) * 100),
        ];
    }
}
