<?php

namespace Tests\Feature;

use App\Enums\ProviderStatus;
use App\Models\City;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MultiShopTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Provider
    {
        return Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
    }

    public function test_merchant_declares_a_second_shop_and_switches(): void
    {
        $first = $this->shop();
        $owner = $first->user;
        $city = City::create(['name' => 'Bobo-Dioulasso', 'slug' => 'bobo-x', 'latitude' => 11.18, 'longitude' => -4.29]);

        $this->actingAs($owner)->get('/pro/boutiques/nouvelle')->assertOk();
        $this->post('/pro/boutiques', [
            'business_name' => 'Pièces Faso Bobo', 'type' => ProviderType::PARTS_SHOP, 'phone' => '71223344', 'city_id' => $city->id,
        ])->assertRedirect('/pro/fiche');

        $second = $owner->providers()->where('business_name', 'Pièces Faso Bobo')->first();
        $this->assertSame(ProviderStatus::Pending, $second->status, 'Chaque boutique est validée par l\'admin.');
        $this->assertEquals(11.18, $second->latitude);

        // La nouvelle boutique devient la boutique active : un produit ajouté va dans son catalogue.
        $this->post('/pro/catalogue', [
            'name' => 'Filtre à huile', 'part_category_id' => PartCategory::firstOrCreate(['slug' => 'filtres-test'], ['name' => 'Filtres'])->id, 'vehicle_types' => ['voiture'],
            'condition' => 'new', 'price' => 2500, 'stock' => 3,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $second->products()->count());
        $this->assertSame(0, $first->products()->count());

        $this->post("/pro/boutiques/{$first->slug}/choisir")->assertRedirect('/pro');
        $this->get('/pro/catalogue')->assertOk()->assertDontSee('Filtre à huile');
        $this->get('/pro/boutiques')->assertOk()->assertSee('Pièces Faso Bobo')->assertSee($first->business_name);

        // Impossible de choisir la boutique de quelqu'un d'autre.
        $this->post('/pro/boutiques/'.$this->shop()->slug.'/choisir')->assertForbidden();
    }

    public function test_shop_limit_and_only_parts_sellers_can_add_shops(): void
    {
        Setting::store(['max_shops_per_owner' => 1]);
        $this->actingAs($this->shop()->user)->get('/pro/boutiques/nouvelle')->assertRedirect('/pro/boutiques');

        Setting::store(['max_shops_per_owner' => 5]);
        $mechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);
        $this->actingAs($mechanic->user)->get('/pro/boutiques/nouvelle')->assertRedirect('/pro/boutiques');
    }

    public function test_owner_adds_employees_with_limited_access(): void
    {
        Notification::fake();
        $shop = $this->shop();
        Product::factory()->for($shop, 'provider')->create(['stock' => 5, 'price' => 1000]);

        $this->actingAs($shop->user)->post('/pro/equipe', ['name' => 'Awa Caisse', 'email' => 'caisse@exemple.bf', 'role' => 'cashier'])->assertSessionHas('success');
        $cashier = User::where('email', 'caisse@exemple.bf')->first();
        $this->assertTrue($cashier->isProvider());
        Notification::assertSentTo($cashier, ResetPassword::class);

        // Une adresse de client existant est refusée.
        $client = User::factory()->create();
        $this->post('/pro/equipe', ['email' => $client->email, 'role' => 'seller'])->assertSessionHasErrors('email');

        // Le caissier ne voit que les ventes de cette boutique.
        $this->post('/deconnexion');
        // Tant que l'email n'est pas confirmé, la boutique reste fermée à l'employé.
        $this->actingAs($cashier->fresh())->get('/pro')->assertRedirect('/email/verifier');
        $cashier->markEmailAsVerified();
        $this->actingAs($cashier->fresh())->get('/pro')->assertOk()->assertSee($shop->business_name);
        $this->get('/pro/gestion/factures/nouvelle')->assertOk();
        $this->get('/pro/gestion/stock')->assertForbidden();
        $this->get('/pro/fiche')->assertForbidden();
        $this->get('/pro/catalogue')->assertForbidden();
        $this->get('/pro/equipe')->assertForbidden();
        $this->get('/pro/gestion')->assertForbidden();

        $this->post('/pro/gestion/factures', [
            'kind' => 'invoice', 'issued_at' => now()->toDateString(),
            'lines' => [['product_id' => $shop->products()->value('id'), 'quantity' => 2, 'unit_price' => 1000]],
        ])->assertRedirect();
        $this->assertSame($cashier->id, $shop->invoices()->first()->user_id);
        $this->assertSame(3, $shop->products()->first()->stock);

        // Promu vendeur : accès au stock ; retiré : plus aucun accès.
        $this->actingAs($shop->user)->put("/pro/equipe/{$cashier->id}", ['role' => 'seller']);
        $this->actingAs($cashier->fresh())->get('/pro/gestion/stock')->assertOk();
        $this->actingAs($shop->user)->delete("/pro/equipe/{$cashier->id}");
        $this->actingAs($cashier->fresh())->get('/pro')->assertRedirect('/mon-compte');
    }

    public function test_employee_with_temporary_password_can_log_in(): void
    {
        $shop = $this->shop();
        $this->actingAs($shop->user)->post('/pro/equipe', ['name' => 'Issa', 'email' => 'issa@exemple.bf', 'role' => 'manager', 'password' => 'provisoire-123']);
        $this->post('/deconnexion');

        $this->post('/connexion', ['login' => 'issa@exemple.bf', 'password' => 'provisoire-123'])->assertRedirect('/pro');
        $this->get('/pro/fiche')->assertRedirect('/email/verifier');
        $issa = User::where('email', 'issa@exemple.bf')->first();
        $issa->markEmailAsVerified();
        $this->actingAs($issa->fresh())->get('/pro/fiche')->assertOk();
        $this->get('/pro/equipe')->assertForbidden();
        $this->get('/pro/boutiques')->assertOk()->assertSee('Gérant');
    }
}
