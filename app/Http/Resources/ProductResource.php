<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availability = $this->availability();

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => ['name' => $this->category->name, 'icon' => $this->category->icon]),
            'brand' => $this->brand,
            'reference' => $this->reference,
            'compatibility' => $this->compatibility,
            'vehicle_types' => $this->vehicle_types,
            'condition' => $this->condition,
            'price' => $this->price,
            'price_label' => $this->priceLabel(),
            'availability' => ['label' => $availability['label'], 'available' => $availability['available']],
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map->url()),
            'distance_m' => $this->when($this->getAttribute('distance_m') !== null, fn () => (int) round($this->distance_m)),
            'whatsapp_url' => $this->whatsappUrl(),
            'shop' => $this->whenLoaded('provider', fn () => [
                'slug' => $this->provider->slug,
                'name' => $this->provider->business_name,
                'phone' => $this->provider->phone,
                'neighborhood' => $this->provider->neighborhood?->name,
                'city' => $this->provider->city?->name,
                'latitude' => $this->provider->latitude,
                'longitude' => $this->provider->longitude,
            ]),
        ];
    }
}
