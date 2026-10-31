<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Villes et quartiers de départ. Les coordonnées des quartiers sont des points
 * centraux approximatifs : l'administrateur peut les corriger depuis son espace.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['Ouagadougou', 12.3714, -1.5197, [
                ['Centre-ville (Koulouba)', 12.3680, -1.5260], ['Ouaga 2000', 12.3236, -1.4930],
                ['Karpala', 12.3270, -1.4580], ["Patte d'Oie", 12.3360, -1.5150],
                ['Dassasgho', 12.3770, -1.4810], ['Tampouy', 12.4010, -1.5510],
                ['Pissy', 12.3420, -1.5650], ['Gounghin', 12.3640, -1.5420],
                ['Cissin', 12.3290, -1.5430], ['Zogona', 12.3850, -1.4980],
                ['Wemtenga', 12.3740, -1.4900], ['Tanghin', 12.4020, -1.5200],
                ['Kalgondin', 12.3480, -1.4840], ['Zone du Bois', 12.3800, -1.4870],
                ['Somgandé', 12.4110, -1.4970], ['Bendogo', 12.3740, -1.4590],
                ['Larlé', 12.3770, -1.5350], ['Kossodo', 12.4250, -1.4700],
                ['Nonsin', 12.4060, -1.5560], ['Saaba', 12.3800, -1.4200],
            ]],
            ['Bobo-Dioulasso', 11.1771, -4.2979, [
                ['Accart-Ville', 11.1800, -4.2950], ['Sarfalao', 11.1550, -4.3100],
                ['Bolomakoté', 11.1720, -4.3150], ['Koko', 11.1870, -4.2800],
                ['Dogona', 11.1650, -4.2850], ['Lafiabougou', 11.1950, -4.3050],
            ]],
            ['Koudougou', 12.2526, -2.3627, []],
            ['Ouahigouya', 13.5828, -2.4216, []],
            ['Kaya', 13.0917, -1.0844, []],
            ['Banfora', 10.6333, -4.7667, []],
            ["Fada N'Gourma", 12.0616, 0.3584, []],
            ['Tenkodogo', 11.7800, -0.3697, []],
            ['Dédougou', 12.4634, -3.4608, []],
        ];

        foreach ($data as [$name, $lat, $lng, $neighborhoods]) {
            $city = City::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'latitude' => $lat, 'longitude' => $lng]
            );

            foreach ($neighborhoods as [$nName, $nLat, $nLng]) {
                $city->neighborhoods()->updateOrCreate(['name' => $nName], ['latitude' => $nLat, 'longitude' => $nLng]);
            }
        }
    }
}
