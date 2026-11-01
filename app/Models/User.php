<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\Lockable;
use App\Support\ShopTeam;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasPushSubscriptions, Lockable;

    /** L'email de confirmation de l'inscription est-il vraiment parti ? (non enregistré en base) */
    public bool $confirmationSent = false;

    /**
     * Le rôle et le statut actif ne sont jamais assignables en masse :
     * ils ne changent que par du code explicite (inscription, administration).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'city_id',
        'neighborhood_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /** Rôle d'administration (permissions), uniquement pour les comptes admin. */
    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'admin_role_id');
    }

    /** Permission d'administration (voir App\Support\Permissions). */
    public function hasPermission(string $permission): bool
    {
        return $this->isAdmin() && $this->is_active && (bool) $this->adminRole?->allows($permission);
    }

    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && (bool) $this->adminRole?->isSuperAdmin();
    }

    /** Session : boutique sur laquelle travaille le compte (commerçant multi-boutiques, employé). */
    public const CURRENT_SHOP = 'current_shop_id';

    private ?Collection $shopsMemo = null;

    /** Première fiche possédée (fiche principale). Voir aussi getProviderAttribute(). */
    public function provider(): HasOne
    {
        return $this->hasOne(Provider::class)->oldest('id');
    }

    /** Toutes les fiches possédées (un commerçant peut déclarer plusieurs boutiques). */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class)->orderBy('id');
    }

    /** Boutiques où le compte est employé (gérant, vendeur, caissier). */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'shop_members')->withPivot('role')->withTimestamps()->orderBy('providers.id');
    }

    /**
     * Boutique « active » : celle choisie dans le sélecteur de l'espace pro, sinon la fiche principale,
     * sinon la première boutique où le compte est employé. Tout l'espace pro passe par $user->provider.
     */
    public function getProviderAttribute(): ?Provider
    {
        $request = request();
        if ($request->hasSession() && auth()->id() === $this->id && ($id = $request->session()->get(self::CURRENT_SHOP))) {
            $shop = $this->shops()->firstWhere('id', (int) $id);
            if ($shop) {
                return $shop;
            }
        }

        return $this->getRelationValue('provider') ?? $this->memberships->first();
    }

    /** Boutiques accessibles : possédées puis employeurs. */
    public function shops(): Collection
    {
        return $this->shopsMemo ??= $this->providers->concat($this->memberships)->unique('id')->values();
    }

    /** owner, manager, seller, cashier ou null. */
    public function shopRole(?Provider $shop): ?string
    {
        if (! $shop) {
            return null;
        }
        if ($shop->user_id === $this->id) {
            return 'owner';
        }

        return $this->memberships->firstWhere('id', $shop->id)?->pivot->role;
    }

    /**
     * Accès à une zone de l'espace pro pour la boutique active : le métier doit avoir le module
     * (voir App\Support\TradeModules) et le rôle du compte doit l'autoriser (voir App\Support\ShopTeam).
     */
    public function canShop(string $area): bool
    {
        return (bool) $this->provider?->type?->allowsArea($area)
            && ShopTeam::allows($this->shopRole($this->provider), $area);
    }

    public function forgetShops(): void
    {
        $this->shopsMemo = null;
        $this->unsetRelation('providers')->unsetRelation('memberships')->unsetRelation('provider');
    }

    /** Numéro pour les SMS et WhatsApp (canaux désactivés par défaut, voir App\Support\Channels). */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->phone;
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->latest();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function partRequests(): HasMany
    {
        return $this->hasMany(PartRequest::class)->latest();
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'favorites')->withPivot('created_at');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isProvider(): bool
    {
        return $this->role === UserRole::Provider;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            UserRole::Admin => route('admin.dashboard'),
            UserRole::Provider => route('pro.dashboard'),
            default => route('account.space'),
        };
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
