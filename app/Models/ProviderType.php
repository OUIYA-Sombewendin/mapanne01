<?php

namespace App\Models;

use App\Models\Concerns\Lockable;
use App\Support\TradeModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type de prestataire (mécanicien, garage, station…), géré depuis l'administration.
 * Expose la même interface que l'ancienne énumération (label(), icon(), value…)
 * pour que les vues restent simples : $provider->type->icon().
 */
class ProviderType extends Model
{
    use Lockable;

    // Identifiants des types livrés par défaut (utilisés par les seeders et les tests).
    public const MECHANIC = 'mecanicien';

    public const GARAGE = 'garage';

    public const TOW_TRUCK = 'depanneur';

    public const STATION = 'station';

    public const PARTS_SHOP = 'commercant';

    protected $fillable = ['slug', 'name', 'short_name', 'icon', 'description', 'can_sell_parts', 'profile_kind', 'modules', 'is_active', 'position'];

    private static ?Collection $cache = null;

    protected function casts(): array
    {
        return [
            'can_sell_parts' => 'boolean',
            'is_active' => 'boolean',
            'modules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Seuls les métiers avec le module « Catalogue » vendent des pièces.
        static::saving(function (self $type) {
            $type->modules = TradeModules::normalize($type->modules ?? []);
            $type->can_sell_parts = in_array('catalog', $type->modules, true);
        });
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        static::$cache = null;
    }

    /** Tous les types, chargés une fois par requête. */
    public static function cached(): Collection
    {
        return static::$cache ??= static::query()->orderBy('position')->orderBy('name')->get()->keyBy('slug');
    }

    public static function bySlug(?string $slug): ?self
    {
        return $slug === null ? null : static::cached()->get($slug);
    }

    /** Types proposés à l'inscription et dans les filtres. */
    public static function active(): Collection
    {
        return static::cached()->where('is_active', true)->values();
    }

    public static function sellingSlugs(): array
    {
        return static::cached()->where('can_sell_parts', true)->keys()->all();
    }

    /** @return array<string, string> slug => libellé */
    public static function options(bool $activeOnly = true): array
    {
        return ($activeOnly ? static::active() : static::cached()->values())
            ->mapWithKeys(fn (self $t) => [$t->slug => $t->name])->all();
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class, 'type', 'slug');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    // Interface compatible avec l'ancienne énumération --------------------------

    public function getValueAttribute(): string
    {
        return $this->slug;
    }

    public function label(): string
    {
        return $this->name;
    }

    public function shortLabel(): string
    {
        return $this->short_name ?: $this->name;
    }

    public function icon(): string
    {
        return $this->icon ?: '🔧';
    }

    public function canSellParts(): bool
    {
        return $this->hasModule('catalog');
    }

    /** Module activé pour ce métier (voir App\Support\TradeModules). */
    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules ?? [], true);
    }

    /** Zone de l'espace pro ouverte pour ce métier (une zone qui n'est pas un module l'est toujours). */
    public function allowsArea(string $area): bool
    {
        return ! in_array($area, TradeModules::keys(), true) || $this->hasModule($area);
    }

    /** Fiche métier rattachée (voir App\Support\ProviderProfiles), ou null. */
    public function profileKind(): ?string
    {
        return $this->profile_kind ?: null;
    }
}
