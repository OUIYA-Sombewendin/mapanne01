<?php

namespace App\Models;

use App\Enums\VehicleType;
use App\Support\AssistProblems;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Demande de dépannage en direct. Cycle : open → accepted → on_way → done
 * (ou cancelled par le conducteur, expired sans réponse).
 */
class AssistRequest extends Model
{
    public const STATUSES = [
        'open' => ['En attente d\'un prestataire', 'bg-amber-100 text-amber-800'],
        'accepted' => ['Acceptée', 'bg-sky-100 text-sky-800'],
        'on_way' => ['Prestataire en route', 'bg-accent-100 text-accent-800'],
        'done' => ['Terminée', 'bg-emerald-100 text-emerald-800'],
        'cancelled' => ['Annulée', 'bg-ink-100 text-ink-600'],
        'expired' => ['Sans réponse', 'bg-red-100 text-red-700'],
    ];

    /** Demandes en cours (pas encore terminées ni fermées). */
    public const ACTIVE = ['open', 'accepted', 'on_way'];

    protected $fillable = [
        'user_id', 'contact_name', 'contact_phone', 'vehicle_type', 'energy', 'problem', 'description',
        'latitude', 'longitude', 'landmark', 'city_id', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accepted_at' => 'datetime',
            'on_way_at' => 'datetime',
            'done_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $request) => $request->token ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** Prestataires prévenus. */
    public function notifiedProviders(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'assist_dispatches')->withPivot(['distance_m', 'declined_at'])->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', self::ACTIVE);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? '';
    }

    public function problemLabel(): string
    {
        return AssistProblems::label($this->problem);
    }

    public function problemIcon(): string
    {
        return AssistProblems::icon($this->problem);
    }

    public function vehicleLabel(): string
    {
        return (VehicleType::tryFrom($this->vehicle_type)?->label() ?? $this->vehicle_type)
            .($this->energy === 'electrique' ? ' électrique' : '');
    }

    public function mapUrl(): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
    }
}
