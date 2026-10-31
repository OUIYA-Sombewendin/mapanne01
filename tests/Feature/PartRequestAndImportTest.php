<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\PartCategory;
use App\Models\PartRequest;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PartRequestAndImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PartCategorySeeder::class);
    }

    public function test_client_request_reaches_shops_of_the_same_city_who_make_offers(): void
    {
        $ouaga = City::create(['name' => 'Ouagadougou', 'slug' => 'ouagadougou', 'latitude' => 12.37, 'longitude' => -1.52]);
        $bobo = City::create(['name' => 'Bobo-Dioulasso', 'slug' => 'bobo', 'latitude' => 11.17, 'longitude' => -4.29]);
        $shopOuaga = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP, 'city_id' => $ouaga->id]);
        $shopBobo = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP, 'city_id' => $bobo->id]);
        $client = User::factory()->create(['phone' => '+22670101010']);

        $this->actingAs($client)->post('/mes-demandes', [
            'title' => 'Rétroviseur gauche Corolla', 'city_id' => $ouaga->id, 'vehicle' => 'Toyota Corolla 2010',
        ])->assertSessionHasNoErrors();
        $request = PartRequest::first();
        $this->assertTrue($request->isOpen());

        $this->actingAs($shopOuaga->user)->get('/pro/demandes')->assertSee('Rétroviseur gauche Corolla');
        $this->actingAs($shopBobo->user)->get('/pro/demandes')->assertDontSee('Rétroviseur gauche Corolla');
        $this->actingAs($shopBobo->user)->post("/pro/demandes/{$request->id}/offre", ['price' => 1000, 'condition' => 'new'])->assertNotFound();

        $this->actingAs($shopOuaga->user)->post("/pro/demandes/{$request->id}/offre", ['price' => 15000, 'condition' => 'used', 'message' => 'Dispo'])
            ->assertSessionHasNoErrors();
        $this->actingAs($client)->get("/mes-demandes/{$request->id}")->assertSee('15 000 FCFA')->assertSee($shopOuaga->business_name);

        // Une seule offre par boutique (mise à jour)
        $this->actingAs($shopOuaga->user)->post("/pro/demandes/{$request->id}/offre", ['price' => 14000, 'condition' => 'used']);
        $this->assertSame(1, $request->offers()->count());

        // Un autre client ne voit pas la demande
        $this->actingAs(User::factory()->create())->get("/mes-demandes/{$request->id}")->assertNotFound();

        $this->actingAs($client)->post("/mes-demandes/{$request->id}/cloturer");
        $this->actingAs($shopOuaga->user)->get('/pro/demandes')->assertDontSee('Rétroviseur gauche Corolla');
    }

    public function test_client_without_phone_must_give_one(): void
    {
        $city = City::create(['name' => 'Ouaga', 'slug' => 'ouaga', 'latitude' => 12.37, 'longitude' => -1.52]);
        $client = User::factory()->create(['phone' => null]);

        $this->actingAs($client)->post('/mes-demandes', ['title' => 'Phare', 'city_id' => $city->id])->assertSessionHasErrors('contact_phone');
    }

    public function test_part_requests_can_be_disabled(): void
    {
        Setting::store(['part_requests_enabled' => false]);

        $this->actingAs(User::factory()->create())->get('/mes-demandes/nouvelle')->assertNotFound();
    }

    public function test_csv_import_creates_and_updates_products(): void
    {
        $shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $shop->products()->create(['part_category_id' => PartCategory::first()->id, 'name' => 'Filtre à huile', 'price' => 4000, 'stock' => 1, 'vehicle_types' => ['voiture']]);

        $csv = "nom;categorie;prix;stock;marque;reference;compatibilite;vehicules;etat;prix_barre;garantie;description\n"
            ."Filtre à huile;lubrifiants-et-filtres;4500;12;Mann;;Corolla;voiture;neuf;;;\n"
            ."Batterie moto;Électricité et batterie;12 000;5;Yuasa;;;moto,tricycle;neuf;15000;6 mois;\n"
            .";freinage;1000;1;;;;;;;;\n"
            ."Pièce inconnue;categorie-qui-nexiste-pas;5000;1;;;;;;;;\n";
        $file = UploadedFile::fake()->createWithContent('catalogue.csv', $csv);

        $this->actingAs($shop->user)->post('/pro/catalogue/import', ['file' => $file])->assertSessionHas('success', '1 pièce(s) ajoutée(s), 1 mise(s) à jour.');
        $this->assertCount(2, session('importErrors'));

        $battery = Product::firstWhere('name', 'Batterie moto');
        $this->assertSame(12000, $battery->price);
        $this->assertSame(15000, $battery->compare_at_price);
        $this->assertSame(20, $battery->discountPercent());
        $this->assertSame(['moto', 'tricycle'], $battery->vehicle_types);
        $this->assertSame(12, Product::firstWhere('name', 'Filtre à huile')->stock);
    }

    public function test_product_premoderation(): void
    {
        Setting::store(['products_premoderation' => true]);
        $shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);

        $this->actingAs($shop->user)->post('/pro/catalogue', [
            'name' => 'Pare-brise', 'part_category_id' => PartCategory::first()->id, 'vehicle_types' => ['voiture'], 'condition' => 'new',
            'price' => 50000, 'stock' => 1, 'is_active' => '1',
        ]);
        $product = Product::first();
        $this->assertTrue($product->isPendingApproval());
        $this->assertSame(0, Product::visible()->count());

        $this->actingAs(User::factory()->admin()->create())->post("/admin/pieces/{$product->slug}/publier");
        $this->assertSame(1, Product::visible()->count());
    }

    public function test_used_parts_can_be_forbidden(): void
    {
        Setting::store(['allow_used_parts' => false]);
        $shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);

        $this->actingAs($shop->user)->post('/pro/catalogue', [
            'name' => 'Moteur', 'part_category_id' => PartCategory::first()->id, 'vehicle_types' => ['voiture'], 'condition' => 'used',
            'price' => 500000, 'stock' => 1, 'is_active' => '1',
        ])->assertSessionHasErrors('condition');
    }
}
