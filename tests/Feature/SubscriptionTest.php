<?php

namespace Tests\Feature;

use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Provider
    {
        return Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
    }

    public function test_everything_is_free_when_subscriptions_are_disabled(): void
    {
        $shop = $this->shop();

        $this->assertTrue($shop->hasPlanFeature('reports'));
        $this->actingAs($shop->user)->get('/pro/gestion/rapports')->assertOk();
        $this->get('/pro/abonnement')->assertNotFound();
    }

    public function test_default_plan_limits_features_when_enabled(): void
    {
        Setting::store(['subscriptions_enabled' => true]);
        $shop = $this->shop();

        $this->actingAs($shop->user)->get('/pro/gestion/factures')->assertRedirect('/pro/abonnement');
        $this->get('/pro/catalogue')->assertOk(); // inclus dans l'offre gratuite
        $this->get('/pro/abonnement')->assertOk()->assertSee('Standard')->assertSee('Premium');
    }

    public function test_request_then_admin_activation_unlocks_features(): void
    {
        Setting::store(['subscriptions_enabled' => true, 'featured_enabled' => true]);
        $shop = $this->shop();
        $premium = Plan::where('slug', 'premium')->first();

        $this->actingAs($shop->user)->post('/pro/abonnement', ['plan_id' => $premium->id]);
        $subscription = $shop->subscriptions()->first();
        $this->assertSame('pending', $subscription->status);
        $this->assertFalse($shop->fresh()->hasPlanFeature('reports'));

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/abonnements/{$subscription->id}/activer", ['amount' => 15000, 'payment_method' => 'Orange Money', 'payment_reference' => 'OM123'])
            ->assertSessionHas('success');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->hasPlanFeature('reports'));
        $this->assertTrue($fresh->is_featured, 'L\'offre Premium inclut la mise à la une.');
        $this->assertSame('OM123', $subscription->fresh()->payment_reference);
        $this->assertTrue($subscription->fresh()->ends_at->isSameDay(now()->addDays(30)));

        // Échéance : retour à l'offre par défaut et fin de la mise à la une.
        $this->travel(31)->days();
        $this->artisan('mapanne:subscriptions')->assertSuccessful();
        $this->assertSame('expired', $subscription->fresh()->status);
        $this->assertFalse($shop->fresh()->hasPlanFeature('reports'));
        $this->assertFalse($shop->fresh()->is_featured);
    }

    public function test_product_limit_of_the_plan(): void
    {
        Setting::store(['subscriptions_enabled' => true]);
        Plan::where('slug', 'gratuit')->update(['limits' => json_encode(['max_products' => 1])]);
        $shop = $this->shop();
        Product::factory()->for($shop, 'provider')->create();

        $this->actingAs($shop->user)->post('/pro/catalogue', [
            'name' => 'Filtre', 'part_category_id' => PartCategory::value('id'), 'vehicle_types' => ['voiture'],
            'condition' => 'new', 'price' => 2000, 'stock' => 1,
        ])->assertRedirect('/pro/abonnement')->assertSessionHas('error');
        $this->assertSame(1, $shop->products()->count());
    }

    public function test_admin_composes_a_plan(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/admin/offres', [
            'name' => 'Garage Pro', 'price' => 7500, 'period_days' => 30,
            'features' => ['invoices', 'expenses'], 'provider_types' => [ProviderType::GARAGE], 'is_active' => '1',
        ])->assertRedirect('/admin/offres');

        $plan = Plan::where('slug', 'garage-pro')->first();
        $this->assertTrue($plan->includes('invoices'));
        $this->assertFalse($plan->appliesTo($this->shop()));
        $this->assertTrue($plan->appliesTo(Provider::factory()->create(['type' => ProviderType::GARAGE])));
    }

    public function test_free_plan_request_is_activated_immediately(): void
    {
        Setting::store(['subscriptions_enabled' => true]);
        $shop = $this->shop();
        $this->actingAs($shop->user)->post('/pro/abonnement', ['plan_id' => Plan::where('slug', 'gratuit')->value('id')]);

        $this->assertSame('active', Subscription::first()->status);
    }
}
