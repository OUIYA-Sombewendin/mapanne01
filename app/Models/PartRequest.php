<?php

namespace App\Models;

use App\Enums\VehicleType;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Demande de pièce : le client décrit la pièce introuvable, les boutiques de sa ville
 * répondent par une offre (prix, état, message). Le client contacte ensuite la boutique.
 */
class PartRequest extends Model
{
    public const STATUSES = ['open' => 'Ouverte', 'closed' => 'Clôturée', 'expired' => 'Expirée'];

    protected $fillable = ['part_category_id', 'city_id', 'title', 'details', 'vehicle', 'vehicle_type', 'contact_phone'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'part_category_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(PartRequestOffer::class)->orderBy('price');
    }

    /** Demandes ouvertes et non expirées. */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function statusLabel(): string
    {
        return $this->status === 'open' && ! $this->isOpen() ? self::STATUSES['expired'] : self::STATUSES[$this->status];
    }

    public function vehicleTypeLabel(): ?string
    {
        return VehicleType::tryFrom((string) $this->vehicle_type)?->label();
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** Numéro à contacter : celui saisi sur la demande, sinon celui du compte. */
    public function contactPhone(): ?string
    {
        return $this->contact_phone ?: $this->user?->phone;
    }

    public function whatsappUrlFor(Provider $shop): ?string
    {
        return Phone::whatsappUrl(
            $this->contactPhone(),
            "Bonjour, c'est {$shop->business_name} (MaPanne). J'ai la pièce « {$this->title} » que vous recherchez."
        );
    }
}
