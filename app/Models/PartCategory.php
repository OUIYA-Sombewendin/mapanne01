<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Catégorie de pièces, sur deux niveaux : catégorie (Freinage) et sous-catégorie (Plaquettes de frein). */
class PartCategory extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'position', 'parent_id'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** Identifiants de la catégorie et de ses sous-catégories (filtre « Freinage » = toutes ses pièces). */
    public function familyIds(): array
    {
        return array_merge([$this->id], self::where('parent_id', $this->id)->pluck('id')->all());
    }

    public static function familyIdsOf(int|string|null $id): array
    {
        return $id ? array_merge([(int) $id], self::where('parent_id', $id)->pluck('id')->all()) : [];
    }

    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /** Catégories et sous-catégories dans l'ordre d'affichage (pour les listes déroulantes). */
    public static function tree(): Collection
    {
        return self::roots()->orderBy('position')->with('children')->get();
    }
}
