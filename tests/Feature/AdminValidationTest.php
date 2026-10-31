<?php

namespace Tests\Feature;

use App\Enums\ProviderStatus;
use App\Models\ActivityLog;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_approves_provider_which_becomes_visible(): void
    {
        $admin = User::factory()->admin()->create();
        $p = Provider::factory()->create();

        $this->actingAs($admin)->post("/admin/prestataires/{$p->slug}/valider")->assertRedirect();

        $p->refresh();
        $this->assertSame(ProviderStatus::Approved, $p->status);
        $this->assertSame($admin->id, $p->approved_by);
        $this->assertTrue(ActivityLog::where('action', 'provider.approved')->exists());
        $this->get('/prestataires/'.$p->slug)->assertOk();
    }

    public function test_reject_requires_a_reason_and_provider_can_resubmit(): void
    {
        $admin = User::factory()->admin()->create();
        $p = Provider::factory()->create();

        $this->actingAs($admin)->post("/admin/prestataires/{$p->slug}/refuser")->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/prestataires/{$p->slug}/refuser", ['reason' => 'Numéro injoignable']);
        $this->assertSame(ProviderStatus::Rejected, $p->refresh()->status);

        $this->actingAs($p->user)->post('/pro/renvoyer');
        $this->assertSame(ProviderStatus::Pending, $p->refresh()->status);
    }

    public function test_provider_cannot_approve_itself(): void
    {
        $p = Provider::factory()->create();

        $this->actingAs($p->user)->post("/admin/prestataires/{$p->slug}/valider")->assertForbidden();
        $this->actingAs($p->user)->put('/pro/fiche', ['status' => 'approved'] + $p->only(['business_name', 'phone', 'latitude', 'longitude']) + ['type' => $p->type->value]);

        $this->assertSame(ProviderStatus::Pending, $p->refresh()->status);
    }

    public function test_suspending_account_revokes_api_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->createToken('phone');

        $this->actingAs($admin)->post("/admin/utilisateurs/{$user->id}/statut");

        $this->assertFalse($user->refresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());
    }
}
