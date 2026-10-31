<?php

namespace Database\Seeders;

use App\Enums\Availability;
use App\Enums\ProviderStatus;
use App\Enums\VehicleType;
use App\Models\Neighborhood;
use App\Models\PartCategory;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Service;
use App\Models\User;
use App\Support\Inventory;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration pour le développement local uniquement.
 * Comptes : voir README.md (mot de passe « password »).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $client = User::factory()->create(['name' => 'Awa Ouédraogo', 'phone' => '+22670000001', 'email' => 'client@mapanne.test']);
        $client->vehicles()->create(['type' => VehicleType::Motorbike, 'brand' => 'Yamaha', 'model' => 'Crypton', 'year' => 2021]);

        $nb = fn (string $name) => Neighborhood::where('name', $name)->firstOrFail();
        $svc = fn (array $slugs) => Service::whereIn('slug', $slugs)->pluck('id');

        $providers = [
            ['Garage Wend-Panga', ProviderType::GARAGE, 'Gounghin', ['mecanique-generale', 'diagnostic-moteur', 'freinage', 'vidange'], '07:30', '18:30', false, true],
            ['Vulcanisation Le Rapide', ProviderType::MECHANIC, 'Dassasgho', ['crevaison-vulcanisation', 'changement-de-pneu'], '18:00', '02:00', true, false],
            ['Dépannage Express 226', ProviderType::TOW_TRUCK, 'Ouaga 2000', ['remorquage', 'depannage-sur-place', 'batterie-recharge-remplacement'], '00:00', '00:00', true, true],
            ['Moto Service Tampouy', ProviderType::MECHANIC, 'Tampouy', ['mecanique-moto', 'vidange', 'electricite-automobile'], '08:00', '19:00', false, false],
            ['Auto Électricité Karpala', ProviderType::MECHANIC, 'Karpala', ['electricite-automobile', 'batterie-recharge-remplacement', 'climatisation'], '08:00', '18:00', false, false],
            ['Station Koulouba Énergie', ProviderType::STATION, 'Centre-ville (Koulouba)', ['super-91', 'gasoil', 'gonflage', 'boutique-et-lubrifiants'], '00:00', '00:00', true, true],
            ['Pièces Auto Faso', ProviderType::PARTS_SHOP, 'Larlé', ['pieces-auto', 'batteries', 'huiles-et-filtres'], '08:00', '18:00', false, true],
            ['Garage Pissy Mécanique', ProviderType::GARAGE, 'Pissy', ['mecanique-generale', 'embrayage-et-boite', 'carrosserie-et-tolerie', 'peinture'], '07:00', '18:00', false, false],
        ];

        foreach ($providers as $i => [$name, $type, $nbName, $services, $opens, $closes, $night, $formal]) {
            $neighborhood = $nb($nbName);
            $provider = Provider::factory()->approved()->create([
                'user_id' => User::factory()->provider()->create(['phone' => '+2267100000'.$i]),
                'business_name' => $name,
                'type' => $type,
                'city_id' => $neighborhood->city_id,
                'neighborhood_id' => $neighborhood->id,
                'address' => $nbName.', Ouagadougou',
                'latitude' => $neighborhood->latitude + (($i % 3) - 1) * 0.003,
                'longitude' => $neighborhood->longitude + (($i % 2) ? 0.002 : -0.002),
                'night_service' => $night,
                'is_formal' => $formal,
                'availability' => $i === 3 ? Availability::Busy : Availability::Available,
                'whatsapp' => '+2267100000'.$i,
            ]);
            $provider->services()->sync($svc($services));

            foreach (range(1, $type === ProviderType::GARAGE ? 6 : 7) as $day) {
                $provider->schedules()->create(['day' => $day, 'opens_at' => $opens, 'closes_at' => $closes]);
            }
        }

        // Avis de démonstration
        $wend = Provider::where('slug', 'garage-wend-panga')->first();
        foreach ([5, 5, 4, 5, 5] as $rating) {
            $wend->reviews()->forceCreate([
                'user_id' => User::factory()->create()->id,
                'rating' => $rating,
                'comment' => 'Travail sérieux et rapide, je recommande.',
            ]);
        }

        $this->seedProducts();

        // Un prestataire en attente : il ne doit apparaître que dans l'espace admin.
        $pending = $nb('Cissin');
        Provider::factory()->create([
            'user_id' => User::factory()->provider()->create(['name' => 'Issa Sawadogo', 'phone' => '+22672000000', 'email' => 'pro@mapanne.test']),
            'business_name' => 'Garage Nouvelle Vision',
            'type' => ProviderType::GARAGE,
            'status' => ProviderStatus::Pending,
            'city_id' => $pending->city_id,
            'neighborhood_id' => $pending->id,
            'latitude' => $pending->latitude,
            'longitude' => $pending->longitude,
        ]);
    }

    private function seedProducts(): void
    {
        $shop = Provider::where('slug', 'pieces-auto-faso')->firstOrFail();
        $garage = Provider::where('slug', 'garage-wend-panga')->firstOrFail();
        $cat = fn (string $slug) => PartCategory::where('slug', $slug)->value('id');

        // [boutique, catégorie, nom, marque, compatibilité, types, état, prix, stock, sur commande]
        $products = [
            [$shop, 'pieces-moteur', 'Bougie d\'allumage', 'NGK', 'Toyota Corolla, Yaris, Hilux essence', ['voiture'], 'new', 2500, 40, false],
            [$shop, 'freinage', 'Plaquettes de frein avant', 'Bosch', 'Toyota Corolla 2008-2014', ['voiture'], 'new', 18000, 6, false],
            [$shop, 'freinage', 'Disque de frein avant (la paire)', 'Valeo', 'Peugeot 206, 207', ['voiture'], 'new', 35000, 2, false],
            [$shop, 'electricite-et-batterie', 'Batterie 12V 70Ah', 'Varta', 'Berlines et SUV', ['voiture'], 'new', 65000, 5, false],
            [$shop, 'electricite-et-batterie', 'Batterie moto 12V 5Ah', 'Yuasa', 'Yamaha Crypton, Honda Wave, Sanili', ['moto', 'tricycle'], 'new', 12000, 15, false],
            [$shop, 'lubrifiants-et-filtres', 'Huile moteur 5W-30 (5 L)', 'Total Quartz', null, ['voiture'], 'new', 22000, 25, false],
            [$shop, 'lubrifiants-et-filtres', 'Filtre à huile', 'Mann', 'Toyota Corolla, RAV4, Hilux', ['voiture'], 'new', 4500, 0, true],
            [$shop, 'pieces-moto-et-tricycle', 'Kit chaîne + pignons', 'DID', 'Yamaha Crypton 110', ['moto'], 'new', 15000, 8, false],
            [$shop, 'pneus-et-jantes', 'Pneu 195/65 R15', 'Michelin', null, ['voiture'], 'new', 55000, 4, false],
            [$shop, 'transmission-et-embrayage', 'Kit embrayage complet', 'Sachs', 'Toyota Hilux 2.5 D-4D', ['voiture', 'camion'], 'used', 90000, 1, false],
            [$garage, 'eclairage', 'Phare avant droit', null, 'Toyota Corolla 2010', ['voiture'], 'used', 25000, 1, false],
            [$garage, 'lubrifiants-et-filtres', 'Filtre à air', 'Mann', 'Toyota Corolla, Yaris', ['voiture'], 'new', 6000, 10, false],
        ];

        foreach ($products as [$provider, $category, $name, $brand, $compat, $types, $condition, $price, $stock, $onOrder]) {
            $provider->products()->create([
                'part_category_id' => $cat($category),
                'name' => $name,
                'brand' => $brand,
                'compatibility' => $compat,
                'vehicle_types' => $types,
                'condition' => $condition,
                'price' => $price,
                'stock' => $stock,
                'on_order' => $onOrder,
                'description' => $condition === 'used'
                    ? 'Pièce d\'occasion contrôlée, en bon état de fonctionnement.'
                    : 'Pièce neuve d\'origine ou de qualité équivalente.',
                // Prix d'achat fictif (~70 % du prix de vente) pour illustrer les marges.
                'cost_price' => (int) round($price * 0.7 / 50) * 50,
                'stock_alert' => 2,
            ]);
        }

        $this->seedManagement();
    }

    /** Quelques ventes, achats et dépenses pour la boutique de démonstration (espace Gestion). */
    private function seedManagement(): void
    {
        $shop = Provider::where('type', ProviderType::PARTS_SHOP)->where('status', ProviderStatus::Approved)->with('products')->first();
        if (! $shop || $shop->products->isEmpty()) {
            return;
        }

        $first = $shop->products->first();
        Inventory::move($first, 'in', 5, 'purchase', $first->cost_price, 'Fournisseur Ouaga Distribution');

        foreach ([[2, 'Issa K.', true], [1, 'Garage Wendkouni', false], [3, null, true]] as $i => [$qty, $customer, $paid]) {
            $product = $shop->products[$i % $shop->products->count()];
            if ($product->stock < $qty) {
                continue;
            }
            $total = $qty * $product->price;
            Inventory::createInvoice($shop, 'invoice', [
                'customer_name' => $customer, 'issued_at' => now()->subDays(6 - $i * 2)->toDateString(),
                'amount_paid' => $paid ? $total : (int) ($total / 2), 'payment_method' => 'Orange Money',
            ], [['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $product->price]]);
        }
        Inventory::createInvoice($shop, 'quote', ['customer_name' => 'Transport Kaboré'], [
            ['product_id' => $first->id, 'quantity' => 4, 'unit_price' => $first->price],
            ['description' => 'Pose et réglage', 'quantity' => 1, 'unit_price' => 5000],
        ]);

        $shop->expenses()->createMany([
            ['spent_at' => now()->startOfMonth(), 'category' => 'Loyer', 'amount' => 75000, 'description' => 'Loyer du magasin'],
            ['spent_at' => now()->subDays(3), 'category' => 'Électricité et eau', 'amount' => 18500, 'description' => 'Facture SONABEL'],
            ['spent_at' => now()->subDay(), 'category' => 'Transport', 'amount' => 6000, 'description' => 'Livraison fournisseur'],
        ]);
    }
}
