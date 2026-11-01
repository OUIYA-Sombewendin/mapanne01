<?php

namespace App\Models;

use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Point de recharge ou d'échange de batterie pour motos et voitures électriques (géré par l'admin). */
class ChargePoint extends Model
{
    public const KINDS = [
        'recharge' => ['Recharge', '🔌'],
        'swap' => ['Échange de batterie', '🔄'],
        'both' => ['Recharge et échange de batterie', '⚡'],
    ];

    protected $fillable = ['name', 'kind', 'city_id', 'address', 'latitude', 'longitude', 'phone', 'vehicles', 'hours', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['vehicles' => 'array', 'is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind][0] ?? $this->kind;
    }

    public function icon(): string
    {
        return self::KINDS[$this->kind][1] ?? '⚡';
    }

    public function vehicleLabels(): array
    {
        return collect($this->vehicles ?? [])->map(fn ($v) => VehicleType::tryFrom($v)?->label())->filter()->values()->all();
    }

    public function mapUrl(): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.$this->latitude.','.$this->longitude;
    }
}
