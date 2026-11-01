<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\User;
use App\Support\TradeModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Chaque métier a son espace admin et ses modules ; l'espace pro suit les modules du métier. */
class TradeModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_trade_gets_its_own_modules_by_default(): void
    {
        $this->assertTrue(ProviderType::bySlug(ProviderType::PARTS_SHOP)->hasModule('stock'));
        $this->assertFalse(ProviderType::bySlug(ProviderType::GARAGE)->canSellParts());
        $this->assertFalse(ProviderType::bySlug(ProviderType::MECHANIC)->hasModule('team'));
        $this->assertTrue(ProviderType::bySlug(ProviderType::STATION)->hasModule('shops'));
    }

    public function test_admin_sees_a_trade_space_with_trade_columns(): void
    {
        $garage = Provider::factory()->approved()->create(['type' => ProviderType::GARAGE, 'business_name' => 'Garage Wend-Kuuni']);
        $garage->saveDetails(['employees' => 6, 'has_diagnostic' => true]);
        Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC, 'business_name' => 'Mécanique Sawadogo']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/metiers/garage')
            ->assertOk()
            ->assertSee('Espace métier')
            ->assertSee('Garage Wend-Kuuni')
            ->assertDontSee('Mécanique Sawadogo')
            ->assertSee('Nombre de mécaniciens')
            ->assertSee('Modules du métier');
    }

    public function test_admin_changes_modules_and_prerequisites_are_enforced(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/metiers/garage/modules', ['modules' => ['services', 'stock', 'invoices']])
            ->assertSessionHas('success');
        // Le stock dépend du catalogue : il n'est pas activé seul.
        $this->assertSame(['services', 'invoices'], ProviderType::bySlug(ProviderType::GARAGE)->modules);

        $this->put('/admin/metiers/garage/modules', ['modules' => ['nimporte']])->assertSessionHasErrors('modules.0');
    }

    public function test_a_viewer_without_referentials_right_cannot_change_modules(): void
    {
        $viewer = User::factory()->admin('moderateur')->create();
        $this->actingAs($viewer)->put('/admin/metiers/garage/modules', ['modules' => TradeModules::keys()])->assertForbidden();
    }

    public function test_pro_space_follows_the_trade_modules(): void
    {
        $mechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);

        $this->actingAs($mechanic->user)->get('/pro')->assertOk()->assertSee('Ventes et factures')->assertDontSee('Équipe');
        $this->get('/pro/equipe')->assertForbidden();

        ProviderType::bySlug(ProviderType::MECHANIC)->update(['modules' => ['services']]);
        $this->actingAs($mechanic->user->fresh())->get('/pro/gestion/factures')->assertForbidden();
        $this->get('/pro')->assertDontSee('Ventes et factures');
    }

    public function test_services_are_kept_when_the_trade_has_no_services_module(): void
    {
        $mechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);
        ProviderType::bySlug(ProviderType::MECHANIC)->update(['modules' => []]);

        $this->actingAs($mechanic->user)->get('/pro/fiche')->assertOk()->assertDontSee('name="services[]"', false);
    }
}
