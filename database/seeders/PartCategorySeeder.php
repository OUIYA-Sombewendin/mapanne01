<?php

namespace Database\Seeders;

use App\Models\PartCategory;
use App\Support\PartCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PartCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Pièces moteur', '🔩'],
            ['Freinage', '🛑'],
            ['Suspension et direction', '🛞'],
            ['Pneus et jantes', '⭕'],
            ['Électricité et batterie', '🔋'],
            ['Carrosserie et vitrage', '🚘'],
            ['Éclairage', '💡'],
            ['Transmission et embrayage', '⚙️'],
            ['Lubrifiants et filtres', '🛢️'],
            ['Pièces moto et tricycle', '🏍️'],
            ['Accessoires et confort', '🎧'],
            ['Outillage', '🧰'],
            ['Autres pièces', '📦'],
        ];

        foreach ($categories as $position => [$name, $icon]) {
            PartCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'position' => $position]
            );
        }

        PartCatalog::seedSubcategories();
    }
}
