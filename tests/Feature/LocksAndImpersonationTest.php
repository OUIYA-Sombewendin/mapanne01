<?php

namespace Tests\Feature;

use App\Enums\ProviderStatus;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\Provider;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocksAndImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_items_cannot_be_changed_from_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = Provider::factory()->create();

        $this->actingAs($admin)->post('/admin/verrous', ['type' => 'provider', 'id' => $provider->id, 'reason' => 'Partenaire officiel'])->assertSessionHas('success');
        $this->assertTrue($provider->fresh()->isLocked());

        $this->post("/admin/prestataires/{$provider->slug}/valider")->assertSessionHas('error');
        $this->assertSame(ProviderStatus::Pending, $provider->fresh()->status);
        $this->get("/admin/prestataires/{$provider->slug}/modifier")->assertRedirect();

        // Une page verrouillée ne peut pas être supprimée.
        $page = Page::where('slug', 'faq')->first();
        $this->post('/admin/verrous', ['type' => 'page', 'id' => $page->id]);
        $this->delete("/admin/contenus/{$page->slug}")->assertSessionHas('error');
        $this->assertNotNull($page->fresh());

        $this->get('/admin/verrous')->assertOk()->assertSee($provider->business_name)->assertSee('Partenaire officiel');

        // Déverrouillé : l'action passe.
        $this->post('/admin/verrous', ['type' => 'provider', 'id' => $provider->id]);
        $this->post("/admin/prestataires/{$provider->slug}/valider");
        $this->assertSame(ProviderStatus::Approved, $provider->fresh()->status);
        $this->assertSame(2, ActivityLog::whereIn('action', ['lock.added', 'lock.removed'])->where('subject_id', $provider->id)->count());
    }

    public function test_locking_requires_its_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->admin('moderateur')->create())
            ->post('/admin/verrous', ['type' => 'user', 'id' => $user->id])->assertForbidden();

        // Le pro peut toujours modifier sa propre fiche même verrouillée côté admin.
        $provider = Provider::factory()->approved()->create();
        $provider->lock()->create(['reason' => 'test']);
        $this->actingAs($provider->user)->get('/pro/fiche')->assertOk();
    }

    public function test_admin_blocks_an_account(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create();

        $this->actingAs($admin)->post("/admin/utilisateurs/{$client->id}/statut")->assertSessionHas('success');
        $this->assertFalse($client->fresh()->is_active);

        $this->post('/deconnexion');
        $this->post('/connexion', ['login' => $client->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_admin_accesses_an_account_and_comes_back(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = Provider::factory()->approved()->create();

        $this->actingAs($admin)->post("/admin/utilisateurs/{$provider->user->id}/acceder")->assertRedirect('/pro');
        $this->assertAuthenticatedAs($provider->user);
        $this->get('/pro')->assertOk()->assertSee('Revenir à mon compte admin');

        // Le mot de passe de la personne ne peut pas être changé pendant l'accès.
        $this->put('/mon-compte/mot-de-passe', ['current_password' => 'password', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp'])->assertSessionHas('error');

        $this->post('/acces/quitter')->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $this->assertSame(1, ActivityLog::where('action', 'user.impersonated')->count());
        $this->assertSame(1, ActivityLog::where('action', 'user.impersonation_ended')->count());
    }

    public function test_access_is_refused_for_admins_blocked_accounts_and_without_permission(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/utilisateurs/'.User::factory()->admin()->create()->id.'/acceder')->assertForbidden();
        $this->post('/admin/utilisateurs/'.User::factory()->inactive()->create()->id.'/acceder')->assertStatus(422);

        $this->actingAs(User::factory()->admin('moderateur')->create())
            ->post('/admin/utilisateurs/'.User::factory()->create()->id.'/acceder')->assertForbidden();

        // Le rôle Support a l'accès (assistance aux utilisateurs).
        $this->assertTrue(Role::where('slug', 'support')->first()->allows('users.impersonate'));

        // Sans accès en cours, « quitter » ne donne rien.
        $this->actingAs(User::factory()->create())->post('/acces/quitter')->assertForbidden();
    }
}
