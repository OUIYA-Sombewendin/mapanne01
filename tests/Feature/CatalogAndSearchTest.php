<?php

namespace Tests\Feature;

use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sous-catégories, compatibilité structurée, code-barres, inventaire physique et recherche améliorée. */
class CatalogAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_category_includes_its_sub_categories(): void
    {
        $brakes = PartCategory::create(['name' => 'Freinage', 'slug' => 'freinage']);
        $pads = PartCategory::create(['name' => 'Plaquettes de frein', 'slug' => 'plaquettes-de-frein', 'parent_id' => $brakes->id]);
        $engine = PartCategory::create(['name' => 'Pièces moteur', 'slug' => 'pieces-moteur']);
        Product::factory()->create(['name' => 'Plaquettes avant', 'part_category_id' => $pads->id]);
        Product::factory()->create(['name' => 'Joint de culasse', 'part_category_id' => $engine->id]);

        $this->get('/pieces?category=freinage')->assertOk()->assertSee('Plaquettes avant')->assertDontSee('Joint de culasse')
            ->assertSee('Plaquettes de frein'); // pastille de sous-catégorie
        $this->get('/pieces?category=plaquettes-de-frein')->assertSee('Plaquettes avant');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/pieces/categories', ['name' => 'Disques', 'parent_id' => $brakes->id])->assertSessionHas('success');
        $this->assertSame($brakes->id, PartCategory::firstWhere('name', 'Disques')->parent_id);
        // Pas de troisième niveau.
        $this->post('/admin/pieces/categories', ['name' => 'Trop profond', 'parent_id' => $pads->id])->assertSessionHasErrors('parent_id');
    }

    public function test_shop_saves_fitments_barcode_and_unit(): void
    {
        $shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $category = PartCategory::create(['name' => 'Freinage', 'slug' => 'freinage']);

        $this->actingAs($shop->user)->post('/pro/catalogue', [
            'name' => 'Plaquettes Corolla', 'part_category_id' => $category->id, 'vehicle_types' => ['voiture'], 'condition' => 'new',
            'price' => 12000, 'stock' => 3, 'is_active' => 1, 'unit' => 'jeu', 'barcode' => ' 3165143 ',
            'fitments' => [
                ['make' => 'toyota', 'model' => 'Corolla', 'year_from' => 2014, 'year_to' => 2008],
                ['make' => '', 'model' => 'ignorée'],
            ],
        ])->assertRedirect('/pro/catalogue');

        $product = Product::firstWhere('name', 'Plaquettes Corolla');
        $this->assertSame(['jeu', '3165143'], [$product->unit, $product->barcode]);
        $fit = $product->fitments()->sole();
        $this->assertSame(['Toyota', 'Corolla', 2008, 2014], [$fit->make, $fit->model, $fit->year_from, $fit->year_to]);

        $this->get('/pieces/'.$product->slug)->assertSee('Toyota Corolla (2008–2014)')->assertSee('/ jeu');
        $this->get('/pieces?q=3165143')->assertSee('Plaquettes Corolla');
    }

    public function test_vehicle_filter_uses_make_model_and_year(): void
    {
        $corolla = Product::factory()->create(['name' => 'Filtre Corolla']);
        $corolla->syncFitments([['make' => 'Toyota', 'model' => 'Corolla', 'year_from' => 2008, 'year_to' => 2014]]);
        $allToyota = Product::factory()->create(['name' => 'Ampoule Toyota']);
        $allToyota->syncFitments([['make' => 'Toyota']]);
        Product::factory()->create(['name' => 'Ancienne fiche', 'compatibility' => 'Toyota Corolla, Yaris']);
        $yamaha = Product::factory()->create(['name' => 'Chaîne Crypton']);
        $yamaha->syncFitments([['make' => 'Yamaha', 'model' => 'Crypton']]);

        $this->get('/pieces?make=toyota&model=corolla&year=2010')->assertSee('Filtre Corolla')->assertSee('Ampoule Toyota')->assertSee('Ancienne fiche')->assertDontSee('Chaîne Crypton');
        $this->get('/pieces?make=Toyota&model=Corolla&year=2019')->assertDontSee('Filtre Corolla')->assertSee('Ampoule Toyota');

        // Raccourci « mon véhicule » depuis les véhicules du client.
        $client = User::factory()->create();
        $client->vehicles()->create(['type' => 'moto', 'energy' => 'thermique', 'brand' => 'Yamaha', 'model' => 'Crypton']);
        $this->actingAs($client)->get('/pieces')->assertSee('make=Yamaha', false);
    }

    public function test_physical_inventory_corrects_counted_parts_only(): void
    {
        $shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $counted = Product::factory()->for($shop, 'provider')->create(['name' => 'Bougie NGK', 'stock' => 10, 'shelf' => 'A1', 'cost_price' => 1000, 'barcode' => '111']);
        $untouched = Product::factory()->for($shop, 'provider')->create(['name' => 'Filtre', 'stock' => 5, 'shelf' => 'A1']);
        Product::factory()->for($shop, 'provider')->create(['name' => 'Pneu', 'stock' => 2, 'shelf' => 'B1']);
        $this->actingAs($shop->user);

        $this->get('/pro/gestion/stock/inventaires')->assertOk()->assertSee('Nouvel inventaire');
        $this->post('/pro/gestion/stock/inventaires', ['scope' => 'shelf', 'shelf' => 'A1'])->assertRedirect();
        $count = StockCount::sole();
        $this->assertSame(2, $count->lines()->count());

        // Un seul inventaire en cours à la fois.
        $this->post('/pro/gestion/stock/inventaires', ['scope' => 'all'])->assertRedirect('/pro/gestion/stock/inventaires/'.$count->id);

        $line = $count->lines()->where('product_id', $counted->id)->sole();
        $this->get('/pro/gestion/stock/inventaires/'.$count->id)->assertOk()->assertSee('Bougie NGK')->assertSee('data-codes="111"', false);
        $this->put('/pro/gestion/stock/inventaires/'.$count->id, ['counted' => [$line->id => 7]])->assertSessionHas('success');
        $this->post('/pro/gestion/stock/inventaires/'.$count->id.'/valider')->assertSessionHas('success');

        $this->assertSame([7, 5], [$counted->fresh()->stock, $untouched->fresh()->stock]);
        $count->refresh();
        $this->assertSame(['applied', 1, -3000], [$count->status, $count->adjusted_count, $count->gap_value]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $counted->id, 'reason' => 'inventory', 'quantity' => -3, 'stock_after' => 7]);

        // Un autre commerçant ne voit pas cet inventaire.
        $other = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $this->actingAs($other->user)->get('/pro/gestion/stock/inventaires/'.$count->id)->assertNotFound();
    }

    public function test_provider_search_understands_vehicle_and_problem(): void
    {
        $repair = ServiceCategory::create(['name' => 'Réparation', 'slug' => 'reparation']);
        $moto = Service::create(['service_category_id' => $repair->id, 'name' => 'Mécanique moto', 'slug' => 'mecanique-moto', 'is_active' => true]);
        $paint = Service::create(['service_category_id' => $repair->id, 'name' => 'Peinture', 'slug' => 'peinture', 'is_active' => true]);

        $motoMechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC, 'business_name' => 'Atelier Deux Roues']);
        $motoMechanic->saveDetails(['vehicle_types' => ['moto']]);
        $motoMechanic->services()->sync([$moto->id]);
        $carMechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC, 'business_name' => 'Auto Service Pro']);
        $carMechanic->saveDetails(['vehicle_types' => ['voiture']]);
        $carMechanic->services()->sync([$moto->id]);
        $painter = Provider::factory()->approved()->create(['type' => ProviderType::GARAGE, 'business_name' => 'Carrosserie Arc-en-ciel']);
        $painter->services()->sync([$paint->id]);

        $this->get('/recherche?vehicle=moto')->assertSee('Atelier Deux Roues')->assertDontSee('Auto Service Pro')->assertSee('Carrosserie Arc-en-ciel');

        $this->get('/recherche?q='.urlencode('ma moto ne démarre pas'))
            ->assertSee('Nous cherchons les pros pour')
            ->assertSee('Atelier Deux Roues')->assertDontSee('Auto Service Pro')->assertDontSee('Carrosserie Arc-en-ciel');

        $this->get('/recherche?problem=carrosserie')->assertSee('Carrosserie Arc-en-ciel')->assertDontSee('Atelier Deux Roues');
    }
}
