<?php

namespace Tests\Feature;

use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\User;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PartCategorySeeder::class);
    }

    private function shop(array $state = []): Provider
    {
        return Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP] + $state);
    }

    public function test_products_of_unapproved_shops_or_hidden_products_are_not_public(): void
    {
        $pendingShop = Provider::factory()->create(['type' => ProviderType::PARTS_SHOP]);
        $fromPending = Product::factory()->for($pendingShop)->create(['name' => 'Piece Fantome']);
        $hidden = Product::factory()->create(['name' => 'Piece Masquee']);
        $hidden->forceFill(['is_hidden' => true])->save();
        $inactive = Product::factory()->create(['name' => 'Piece Retiree', 'is_active' => false]);
        Product::factory()->create(['name' => 'Piece Visible']);

        $this->get('/pieces')->assertOk()->assertSee('Piece Visible')
            ->assertDontSee('Piece Fantome')->assertDontSee('Piece Masquee')->assertDontSee('Piece Retiree');

        foreach ([$fromPending, $hidden, $inactive] as $product) {
            $this->get('/pieces/'.$product->slug)->assertNotFound();
            $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        }
        $this->assertSame(1, count($this->getJson('/api/v1/products')->json('data')));
    }

    public function test_search_by_words_vehicle_type_and_price(): void
    {
        Product::factory()->create(['name' => 'Plaquettes de frein avant', 'compatibility' => 'Toyota Corolla 2010', 'price' => 18000]);
        Product::factory()->create(['name' => 'Batterie moto 12V', 'vehicle_types' => ['moto'], 'price' => 12000]);

        $names = fn (string $qs) => collect($this->getJson('/api/v1/products?'.$qs)->json('data'))->pluck('name')->all();

        $this->assertSame(['Plaquettes de frein avant'], $names('q=plaquette corolla'));
        $this->assertSame(['Batterie moto 12V'], $names('vehicle=moto'));
        $this->assertSame(['Batterie moto 12V'], $names('price_max=15000'));
    }

    public function test_search_near_me_orders_by_shop_distance(): void
    {
        $near = $this->shop(['latitude' => 12.372, 'longitude' => -1.520]);
        $far = $this->shop(['latitude' => 12.420, 'longitude' => -1.470]);
        Product::factory()->for($far)->create(['name' => 'Filtre loin']);
        Product::factory()->for($near)->create(['name' => 'Filtre proche']);

        $data = $this->getJson('/api/v1/products?q=filtre&lat=12.3714&lng=-1.5197')->json('data');

        $this->assertSame(['Filtre proche', 'Filtre loin'], array_column($data, 'name'));
        $this->assertLessThan($data[1]['distance_m'], $data[0]['distance_m']);
    }

    public function test_seller_manages_catalog_with_photos(): void
    {
        Storage::fake('public');
        $shop = $this->shop();

        $this->actingAs($shop->user)->post('/pro/catalogue', [
            'name' => 'Kit embrayage',
            'part_category_id' => PartCategory::first()->id,
            'vehicle_types' => ['voiture', 'camion'],
            'condition' => 'used',
            'price' => 90000,
            'stock' => 1,
            'is_active' => '1',
            'photos' => [UploadedFile::fake()->image('kit.jpg')],
        ])->assertRedirect('/pro/catalogue')->assertSessionHasNoErrors();

        $product = $shop->products()->first();
        $this->assertSame(['voiture', 'camion'], $product->vehicle_types);
        $this->assertCount(1, $product->photos);
        Storage::disk('public')->assertExists($product->photos->first()->path);

        $this->actingAs($shop->user)->patch("/pro/catalogue/{$product->slug}/stock", ['stock' => 0, 'price' => 85000]);
        $product->refresh();
        $this->assertSame(85000, $product->price);
        $this->assertSame('Rupture de stock', $product->availability()['label']);
    }

    public function test_seller_cannot_touch_another_shop_products_or_moderation(): void
    {
        $product = Product::factory()->create();
        $other = $this->shop();

        $this->actingAs($other->user)->get("/pro/catalogue/{$product->slug}/modifier")->assertForbidden();
        $this->actingAs($other->user)->delete("/pro/catalogue/{$product->slug}")->assertForbidden();
        $this->assertModelExists($product);

        // is_hidden n'est pas modifiable par le vendeur
        $this->actingAs($product->provider->user)->put("/pro/catalogue/{$product->slug}", [
            'name' => $product->name, 'part_category_id' => $product->part_category_id, 'vehicle_types' => ['voiture'],
            'condition' => 'new', 'price' => 1000, 'stock' => 1, 'is_active' => '1', 'is_hidden' => '1',
        ]);
        $this->assertFalse($product->refresh()->is_hidden);
    }

    public function test_only_trades_with_the_catalog_module_have_a_catalog(): void
    {
        $mechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);
        $garage = Provider::factory()->approved()->create(['type' => ProviderType::GARAGE]);

        $this->actingAs($mechanic->user)->get('/pro/catalogue')->assertForbidden();
        $this->actingAs($garage->user)->get('/pro/catalogue')->assertForbidden();
        $this->actingAs($garage->user)->get('/pro')->assertOk()->assertDontSee('Catalogue');

        // L'admin peut ouvrir le catalogue à un métier.
        ProviderType::bySlug(ProviderType::GARAGE)->update(['modules' => ['services', 'catalog']]);
        $this->actingAs($garage->user->fresh())->get('/pro/catalogue')->assertOk();
    }

    public function test_products_disappear_when_shop_changes_to_a_non_selling_type(): void
    {
        $product = Product::factory()->create();
        $product->provider->update(['type' => ProviderType::MECHANIC]);

        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        $this->assertSame(0, Product::visible()->count());
    }

    public function test_admin_hides_product_with_reason(): void
    {
        $product = Product::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post("/admin/pieces/{$product->slug}/masquer")->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/pieces/{$product->slug}/masquer", ['reason' => 'Contrefaçon']);

        $this->assertTrue($product->refresh()->is_hidden);
        // L'admin garde un aperçu ; le public (API sans session) ne le voit plus.
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        $this->assertSame(0, Product::visible()->count());
    }

    public function test_contact_is_counted_once_per_visitor(): void
    {
        $product = Product::factory()->create();

        $this->post("/pieces/{$product->slug}/contact")->assertNoContent();
        $this->post("/pieces/{$product->slug}/contact")->assertNoContent();

        $this->assertSame(1, $product->refresh()->contacts_count);
    }

    public function test_pages_render(): void
    {
        $product = Product::factory()->create();

        $this->get('/')->assertOk();
        $this->get('/pieces?category=freinage&sort=price_asc')->assertOk();
        $this->get('/pieces/'.$product->slug)->assertOk()->assertSee($product->priceLabel());
        $this->get('/prestataires/'.$product->provider->slug)->assertOk()->assertSee('Pièces en vente');
        $this->actingAs($product->provider->user)->get('/pro/catalogue/nouveau')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/pieces')->assertOk();
    }
}
