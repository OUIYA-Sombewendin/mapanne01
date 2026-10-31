<?php

namespace Tests\Feature;

use App\Enums\ProviderStatus;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règle fondamentale : un prestataire non validé n'apparaît jamais publiquement.
 */
class ProviderVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_provider_is_hidden_from_search_profile_and_api(): void
    {
        $pending = Provider::factory()->create(['business_name' => 'Garage Fantome']);

        $this->get('/recherche?q=Fantome')->assertOk()->assertDontSee('Garage Fantome');
        $this->get('/prestataires/'.$pending->slug)->assertNotFound();
        $this->getJson('/api/v1/providers?q=Fantome')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/providers/'.$pending->slug)->assertNotFound();
    }

    public function test_rejected_and_suspended_providers_are_hidden(): void
    {
        foreach ([ProviderStatus::Rejected, ProviderStatus::Suspended] as $status) {
            $p = Provider::factory()->create(['status' => $status]);
            $this->get('/prestataires/'.$p->slug)->assertNotFound();
        }

        $this->assertSame(0, Provider::visible()->count());
    }

    public function test_approved_provider_with_suspended_account_is_hidden(): void
    {
        $p = Provider::factory()->approved()->create(['user_id' => User::factory()->provider()->inactive()]);

        $this->get('/prestataires/'.$p->slug)->assertNotFound();
    }

    public function test_approved_provider_is_visible(): void
    {
        $p = Provider::factory()->approved()->create(['business_name' => 'Garage Visible']);

        $this->get('/recherche?q=Visible')->assertSee('Garage Visible');
        $this->get('/prestataires/'.$p->slug)->assertOk()->assertSee('Garage Visible');
    }

    public function test_owner_and_admin_can_preview_pending_profile(): void
    {
        $p = Provider::factory()->create();

        $this->actingAs($p->user)->get('/prestataires/'.$p->slug)->assertOk()->assertSee('Aperçu privé');
        $this->actingAs(User::factory()->admin()->create())->get('/prestataires/'.$p->slug)->assertOk();
        $this->actingAs(User::factory()->create())->get('/prestataires/'.$p->slug)->assertNotFound();
    }
}
