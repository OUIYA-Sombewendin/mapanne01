<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Véhicule compatible avec une pièce : marque, modèle (facultatif) et années (facultatives). */
class ProductFitment extends Model
{
    public $timestamps = false;

    protected $fillable = ['make', 'model', 'year_from', 'year_to'];

    protected function casts(): array
    {
        return ['year_from' => 'integer', 'year_to' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Compatibilité avec le véhicule cherché. Un modèle ou des années non renseignés
     * valent « tous » : une pièce « Toyota » sans modèle convient à toutes les Toyota.
     */
    public static function applyMatch(Builder $query, string $make, ?string $model, ?int $year): void
    {
        $query->where('make', 'ilike', $make)
            ->when(filled($model), fn ($q) => $q->where(fn ($w) => $w->whereNull('model')->orWhere('model', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], trim($model)).'%')))
            ->when($year, fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('year_from')->orWhere('year_from', '<=', $year))
                ->where(fn ($w) => $w->whereNull('year_to')->orWhere('year_to', '>=', $year)));
    }

    public function label(): string
    {
        $years = match (true) {
            $this->year_from && $this->year_to => $this->year_from === $this->year_to ? (string) $this->year_from : $this->year_from.'–'.$this->year_to,
            (bool) $this->year_from => 'depuis '.$this->year_from,
            (bool) $this->year_to => 'jusqu\'à '.$this->year_to,
            default => null,
        };

        return trim($this->make.' '.($this->model ?? 'tous modèles').($years ? ' ('.$years.')' : ''));
    }
}
