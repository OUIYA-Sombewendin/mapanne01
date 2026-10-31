<?php

namespace Database\Factories;

use App\Enums\ProviderStatus;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->provider(),
            'type' => fake()->randomElement(ProviderType::cached()->keys()->all()),
            'business_name' => 'Garage '.fake()->unique()->lastName(),
            'description' => fake()->randomElement([
                'Atelier expérimenté, intervention rapide sur place ou à l\'atelier. Devis gratuit avant travaux.',
                'Plus de 10 ans d\'expérience sur toutes marques. Travail soigné et pièces de qualité.',
                'Accueil 7j/7, diagnostic rapide et conseils honnêtes. Paiement Orange Money accepté.',
            ]),
            'phone' => '+226'.fake()->numerify('7#######'),
            // Autour du centre de Ouagadougou
            'latitude' => 12.3714 + fake()->randomFloat(4, -0.04, 0.04),
            'longitude' => -1.5197 + fake()->randomFloat(4, -0.04, 0.04),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ProviderStatus::Approved, 'approved_at' => now()]);
    }

    public function at(float $lat, float $lng): static
    {
        return $this->state(fn () => ['latitude' => $lat, 'longitude' => $lng]);
    }
}
