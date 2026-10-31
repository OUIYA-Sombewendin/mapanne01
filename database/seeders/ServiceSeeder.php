<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['Réparation', '🔧', ['Mécanique générale', 'Mécanique moto', 'Électricité automobile', 'Diagnostic moteur', 'Freinage', 'Vidange', 'Embrayage et boîte', 'Climatisation']],
            ['Pneumatique', '🛞', ['Crevaison / vulcanisation', 'Changement de pneu', 'Équilibrage et parallélisme']],
            ['Assistance', '🚨', ['Batterie (recharge, remplacement)', 'Remorquage', 'Dépannage sur place']],
            ['Carrosserie', '🎨', ['Carrosserie et tôlerie', 'Peinture', 'Vitrage', 'Lavage']],
            ['Station', '⛽', ['Super 91', 'Gasoil', 'Gonflage', 'Boutique et lubrifiants']],
            ['Pièces détachées', '⚙️', ['Pièces auto', 'Pièces moto et tricycle', 'Pneus et jantes', 'Batteries', 'Huiles et filtres']],
        ];

        foreach ($catalog as $position => [$name, $icon, $services]) {
            $category = ServiceCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'position' => $position]
            );

            foreach ($services as $service) {
                $category->services()->updateOrCreate(['slug' => Str::slug($service)], ['name' => $service]);
            }
        }
    }
}
