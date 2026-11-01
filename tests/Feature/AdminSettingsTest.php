<?php

namespace Tests\Feature;

use App\Enums\ProviderStatus;
use App\Models\City;
use App\Models\Page;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ProviderDecision;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tout ce qui doit être pilotable depuis l'administration, sans toucher au code.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_saves_settings_of_every_type(): void
    {
        $this->actingAs($this->admin())->put('/admin/reglages/search', [
            'search_radii' => '3, 7, 15',
            'default_radius' => 7,
            'per_page' => 12,
            'map_default_lat' => 11.18,
            'map_default_lng' => -4.29,
            'featured_enabled' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame([3, 7, 15], setting('search_radii'));
        $this->assertSame(7, setting('default_radius'));
        $this->assertTrue(feature('featured_enabled'));

        $this->put('/admin/reglages/emergency', ['emergency_numbers' => "17 | Police\n18 | Pompiers"]);
        $this->assertSame(['17' => 'Police', '18' => 'Pompiers'], setting('emergency_numbers'));
        $this->get('/urgence')->assertSee('Pompiers');

        // Les secrets sont chiffrés en base et relus en clair.
        $this->put('/admin/reglages/mail', ['mail_mailer' => 'log', 'mail_password' => 's3cret', 'mail_port' => 587, 'mail_encryption' => 'tls']);
        $this->assertSame('s3cret', setting('mail_password'));
        $this->assertNotSame('s3cret', Setting::find('mail_password')->value);
    }

    public function test_settings_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/reglages')->assertForbidden();
        $this->actingAs(User::factory()->create())->put('/admin/reglages/general', [])->assertForbidden();
    }

    public function test_maintenance_mode_closes_site_but_not_admin(): void
    {
        Setting::store(['maintenance_enabled' => true, 'maintenance_message' => 'On revient vite']);

        $this->get('/')->assertStatus(503)->assertSee('On revient vite');
        $this->getJson('/api/v1/providers')->assertStatus(503);
        $this->get('/connexion')->assertOk();
        $this->actingAs($this->admin())->get('/')->assertOk();
    }

    public function test_registrations_can_be_closed(): void
    {
        Setting::store(['registration_clients_enabled' => false]);

        $this->post('/inscription', [
            'account_type' => 'client', 'name' => 'X', 'email' => 'x@exemple.bf',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse', 'terms' => '1',
        ])->assertSessionHasErrors('account_type');
        $this->assertSame(0, User::count());
    }

    public function test_email_verification_can_be_required(): void
    {
        $user = User::factory()->unverified()->create();
        $provider = Provider::factory()->approved()->create();

        $this->actingAs($user)->post("/prestataires/{$provider->slug}/avis", ['rating' => 5])->assertRedirect(); // option désactivée : accepté
        Setting::store(['require_email_verification' => true]);
        $this->actingAs($user)->post("/prestataires/{$provider->slug}/avis", ['rating' => 4])->assertRedirect('/email/verifier');
    }

    public function test_admin_adds_a_provider_type_used_at_registration(): void
    {
        $this->actingAs($this->admin())->post('/admin/types', ['name' => 'Laveur auto', 'icon' => '🧽', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->post('/deconnexion');

        $this->assertNotNull(ProviderType::bySlug('laveur-auto'));
        $this->get('/inscription?type=pro')->assertSee('Laveur auto');

        // Désactivé : plus proposé à l'inscription.
        ProviderType::bySlug('laveur-auto')->update(['is_active' => false]);
        $this->get('/inscription?type=pro')->assertDontSee('Laveur auto');
    }

    public function test_selling_right_follows_the_type_setting(): void
    {
        $product = Product::factory()->create();
        $type = ProviderType::bySlug(ProviderType::PARTS_SHOP);
        $type->update(['modules' => ['services', 'stock']]);

        // Sans catalogue, plus de vente ; le stock (qui en dépend) est retiré aussi.
        $this->assertSame(0, Product::visible()->count());
        $this->assertSame(['services'], $type->fresh()->modules);
        $type->update(['modules' => ['services', 'catalog']]);
        $this->assertSame(1, Product::visible()->count());
        $this->assertTrue($product->fresh()->isVisible());
    }

    public function test_content_pages_are_editable_and_safe(): void
    {
        $page = Page::where('slug', 'a-propos')->first();

        $this->actingAs($this->admin())->put("/admin/contenus/{$page->slug}", [
            'title' => 'Qui sommes-nous', 'slug' => 'a-propos', 'body' => "## Mission\n\n<script>alert(1)</script>", 'is_published' => '1',
        ])->assertRedirect();

        $this->get('/pages/a-propos')->assertSee('Qui sommes-nous')->assertSee('Mission')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_marketplace_can_be_disabled(): void
    {
        $product = Product::factory()->create();
        Setting::store(['marketplace_enabled' => false]);

        $this->get('/pieces')->assertNotFound();
        $this->get('/pieces/'.$product->slug)->assertNotFound();
        // Le catalogue reste l'inventaire de la boutique (gestion), même sans marketplace public.
        $this->actingAs($product->provider->user)->get('/pro/catalogue')->assertOk();
    }

    public function test_admin_promotes_a_client_to_admin_but_not_himself(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create();

        $moderator = Role::where('slug', 'moderateur')->first();

        $this->actingAs($admin)->post("/admin/utilisateurs/{$client->id}/role", ['admin_role_id' => $moderator->id]);
        $this->assertTrue($client->fresh()->isAdmin());
        $this->assertSame($moderator->id, $client->fresh()->admin_role_id);

        // Retrait des droits : redevient client.
        $this->actingAs($admin)->post("/admin/utilisateurs/{$client->id}/role", ['admin_role_id' => null]);
        $this->assertFalse($client->fresh()->isAdmin());

        $this->actingAs($admin)->post("/admin/utilisateurs/{$admin->id}/role")->assertStatus(422);
    }

    public function test_admin_edits_provider_and_decision_is_emailed(): void
    {
        Notification::fake();
        $provider = Provider::factory()->create();

        $this->actingAs($this->admin())->post("/admin/prestataires/{$provider->slug}/valider");

        Notification::assertSentTo($provider->user, ProviderDecision::class);
        $this->assertSame(ProviderStatus::Approved, $provider->fresh()->status);
    }

    public function test_sensitive_changes_are_flagged_and_can_unpublish(): void
    {
        $provider = Provider::factory()->approved()->create(['city_id' => null]);
        $city = City::create(['name' => 'Ouagadougou', 'slug' => 'ouaga', 'latitude' => 12.37, 'longitude' => -1.52]);
        $payload = fn (array $over = []) => $over + [
            'business_name' => $provider->business_name, 'type' => $provider->type->slug, 'phone' => $provider->phone,
            'city_id' => $city->id, 'latitude' => $provider->latitude, 'longitude' => $provider->longitude,
        ];

        // Changement non sensible (description) : rien à signaler.
        $this->actingAs($provider->user)->put('/pro/fiche', $payload(['description' => 'Nouvelle description']));
        $this->assertFalse($provider->fresh()->needs_review);

        // Changement de nom : signalé à l'admin, fiche toujours visible.
        $this->actingAs($provider->user)->put('/pro/fiche', $payload(['business_name' => 'Autre Nom']));
        $fresh = $provider->fresh();
        $this->assertTrue($fresh->needs_review);
        $this->assertArrayHasKey('business_name', $fresh->pending_changes);
        $this->assertSame(ProviderStatus::Approved, $fresh->status);

        // Avec l'option de dépublication : la fiche repasse en attente.
        Setting::store(['sensitive_changes_unpublish' => true]);
        $this->actingAs($provider->user)->put('/pro/fiche', $payload(['business_name' => 'Encore Autre', 'phone' => '71999999']));
        $this->assertSame(ProviderStatus::Pending, $provider->fresh()->status);
    }

    public function test_review_premoderation(): void
    {
        Setting::store(['reviews_premoderation' => true]);
        $provider = Provider::factory()->approved()->create();

        $this->actingAs(User::factory()->create())->post("/prestataires/{$provider->slug}/avis", ['rating' => 5, 'comment' => 'Avis en attente']);
        $this->assertSame(0, $provider->fresh()->reviews_count);
        $this->get('/prestataires/'.$provider->slug)->assertSee('en attente de validation'); // l'auteur est prévenu
        auth()->guard('web')->logout();
        $this->get('/prestataires/'.$provider->slug)->assertDontSee('Avis en attente'); // le public ne le voit pas

        $review = $provider->reviews()->first();
        $this->actingAs($this->admin())->post("/admin/avis/{$review->id}/publier");
        $this->assertSame(1, $provider->fresh()->reviews_count);
    }

    public function test_password_reset_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'oubli@exemple.bf']);

        $this->post('/mot-de-passe-oublie', ['email' => 'oubli@exemple.bf'])->assertSessionHas('success');
        // Même message pour un email inconnu (on ne révèle pas les comptes existants).
        $this->post('/mot-de-passe-oublie', ['email' => 'inconnu@exemple.bf'])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/reinitialiser-mot-de-passe', [
            'token' => $token, 'email' => 'oubli@exemple.bf', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp',
        ])->assertRedirect('/connexion');

        $this->post('/connexion', ['login' => 'oubli@exemple.bf', 'password' => 'nouveau-mdp'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
}
