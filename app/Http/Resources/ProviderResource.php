<?php

namespace App\Http\Resources;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Provider */
class ProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->business_name,
            'type' => ['value' => $this->type->value, 'label' => $this->type->label()],
            'description' => $this->description,
            'phone' => $this->phone,
            'whatsapp_url' => $this->whatsappUrl(),
            'address' => $this->address,
            'city' => $this->whenLoaded('city', fn () => $this->city?->name),
            'neighborhood' => $this->whenLoaded('neighborhood', fn () => $this->neighborhood?->name),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_m' => $this->when($this->getAttribute('distance_m') !== null, fn () => (int) round($this->distance_m)),
            'availability' => $this->availability->value,
            'open_now' => $this->whenLoaded('schedules', fn () => $this->isOpenAt(now())),
            'night_service' => $this->night_service,
            'is_formal' => $this->is_formal,
            'rating' => $this->rating_avg,
            'reviews_count' => $this->reviews_count,
            'logo_url' => $this->logoUrl(),
            'badges' => array_column($this->badges(), 'label'),
            'services' => $this->whenLoaded('services', fn () => $this->services->pluck('name')),
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn ($s) => [
                'day' => $s->day, 'opens_at' => substr($s->opens_at, 0, 5), 'closes_at' => substr($s->closes_at, 0, 5),
            ])),
        ];
    }
}
