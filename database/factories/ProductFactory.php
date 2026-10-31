<?php

namespace Database\Factories;

use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory()->approved()->state(['type' => ProviderType::PARTS_SHOP]),
            'part_category_id' => fn () => PartCategory::first()?->id ?? PartCategory::create([
                'name' => 'Pièces moteur', 'slug' => 'pieces-moteur-'.Str::random(4),
            ])->id,
            'name' => 'Pièce '.fake()->unique()->word(),
            'price' => fake()->numberBetween(2, 200) * 500,
            'stock' => fake()->numberBetween(1, 20),
            'vehicle_types' => ['voiture'],
            'condition' => 'new',
        ];
    }
}
