<?php

namespace App\Models;

use App\Models\Concerns\Lockable;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rôle de l'équipe d'administration (super-admin, modérateur, commercial…). */
class Role extends Model
{
    use Lockable;

    public const SUPER_ADMIN = 'super-admin';

    protected $fillable = ['slug', 'name', 'description', 'color', 'permissions'];

    /** Couleurs de badge proposées : clé => [libellé, classes]. */
    public const COLORS = [
        'gray' => ['Gris', 'bg-ink-200 text-ink-800'],
        'ink' => ['Bleu nuit', 'on-color bg-ink-800 text-white'],
        'amber' => ['Jaune', 'bg-amber-400 text-amber-950'],
        'orange' => ['Orange', 'on-color bg-brand-500 text-white'],
        'sky' => ['Bleu', 'on-color bg-sky-600 text-white'],
        'emerald' => ['Vert', 'on-color bg-emerald-600 text-white'],
        'teal' => ['Turquoise', 'on-color bg-teal-600 text-white'],
        'violet' => ['Violet', 'on-color bg-violet-600 text-white'],
        'rose' => ['Rose', 'on-color bg-rose-600 text-white'],
        'red' => ['Rouge', 'on-color bg-red-600 text-white'],
    ];

    public function badgeClass(): string
    {
        return self::COLORS[$this->color][1] ?? self::COLORS['gray'][1];
    }

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'admin_role_id');
    }

    public static function superAdmin(): ?self
    {
        return static::where('slug', self::SUPER_ADMIN)->first();
    }

    public function isSuperAdmin(): bool
    {
        return in_array('*', $this->permissions ?? [], true);
    }

    public function allows(string $permission): bool
    {
        return $this->isSuperAdmin() || in_array($permission, $this->permissions ?? [], true);
    }

    /** @return list<string> libellés lisibles */
    public function permissionLabels(): array
    {
        if ($this->isSuperAdmin()) {
            return ['Toutes les permissions'];
        }

        return collect($this->permissions)->map(fn ($p) => Permissions::all()[$p] ?? null)->filter()->values()->all();
    }
}
