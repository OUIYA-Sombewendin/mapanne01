<?php

namespace Tests\Feature;

use App\Models\AssistRequest;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Espace client, pages de connexion épurées et envois d'emails annoncés honnêtement. */
class ClientSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_finds_everything_in_his_space(): void
    {
        $client = User::factory()->create(['name' => 'Awa Kaboré']);

        // Après connexion, et même en revenant sur l'accueil, le client arrive sur son tableau de bord.
        $this->post('/connexion', ['login' => $client->email, 'password' => 'password'])->assertRedirect('/mon-espace');
        $this->get('/')->assertRedirect('/mon-espace');
        $this->get('/mon-espace')->assertOk()->assertSee('Awa 👋')->assertSee('Mes dépannages')->assertSee('Profil et sécurité');

        // Les pages publiques s'affichent dans le même cadre (menu de l'espace client).
        $this->get('/recherche')->assertOk()->assertSee('Tableau de bord')->assertSee('Mes favoris');

        foreach (['/mes-depannages', '/mes-vehicules', '/mes-favoris', '/mes-avis', '/mon-compte'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_dashboard_shows_what_needs_attention(): void
    {
        $client = User::factory()->create();
        $client->vehicles()->create(['type' => 'moto', 'energy' => 'thermique', 'brand' => 'Yamaha', 'model' => 'Crypton'])
            ->reminders()->create(['task' => 'vidange', 'label' => 'Vidange', 'every_months' => 3, 'next_due_on' => now()->addDays(2)]);
        $assist = AssistRequest::create([
            'user_id' => $client->id, 'vehicle_type' => 'moto', 'energy' => 'thermique', 'problem' => 'crevaison',
            'latitude' => 12.37, 'longitude' => -1.52, 'contact_name' => 'Awa', 'contact_phone' => '+22670112233',
        ]);

        $this->actingAs($client)->get('/mon-espace')
            ->assertSee('Dépannage en cours')->assertSee(route('assist.show', $assist), false)
            ->assertSee('Vidange');
        $this->get('/mes-depannages')->assertSee($assist->problemLabel());
    }

    public function test_client_space_is_for_clients_only(): void
    {
        $pro = Provider::factory()->approved()->create();
        $this->actingAs($pro->user)->get('/mon-espace')->assertForbidden();
        // Un pro garde l'en-tête public du site.
        $this->get('/recherche')->assertOk()->assertDontSee('Mes dépannages');
    }

    public function test_auth_pages_are_focused_and_terms_are_required(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Retour au site')->assertDontSee('Trouver un pro')
            ->assertSee('Afficher le mot de passe', false);
        $this->get('/inscription')->assertOk()->assertSee(':disabled="!terms"', false);

        $this->post('/inscription', [
            'account_type' => 'client', 'name' => 'Sans Cgu', 'email' => 'sans@cgu.bf',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ])->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_email_is_announced_as_sent_only_when_it_really_leaves(): void
    {
        Notification::fake();
        $client = User::factory()->unverified()->create();

        // Mode « journal » : rien ne part, on le dit.
        config(['mail.default' => 'log']);
        $this->actingAs($client)->post('/email/renvoyer')->assertSessionHas('error');
        $this->get('/email/verifier')->assertSee('pas encore en service');

        // Serveur SMTP configuré : l'envoi est annoncé.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.exemple.bf']);
        $this->post('/email/renvoyer')->assertSessionHas('success');
    }

    public function test_admin_sees_delivery_status(): void
    {
        config(['mail.default' => 'log']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/reglages/mail')->assertOk()->assertSee('État des envois')->assertSee('Inactif');
        $this->post('/admin/reglages/test-email', ['email' => 'test@exemple.bf'])->assertSessionHas('error');
    }
}
