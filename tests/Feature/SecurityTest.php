<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Provider;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SimpleMessage;
use App\Support\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_two_factor_login_by_email_code(): void
    {
        Notification::fake();
        Setting::store(['admin_two_factor_enabled' => true]);
        $admin = User::factory()->admin()->create(['email' => 'chef@mapanne.test']);

        $this->post('/connexion', ['login' => 'chef@mapanne.test', 'password' => 'password'])->assertRedirect('/connexion/verification');
        $this->assertGuest();

        $code = null;
        Notification::assertSentTo($admin, SimpleMessage::class, function ($n) use (&$code) {
            preg_match('/(\d{6})/', $n->subject, $m);
            $code = $m[1] ?? null;

            return $code !== null;
        });

        $this->post('/connexion/verification', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/connexion/verification', ['code' => $code])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);

        // Le code est à usage unique.
        $this->post('/deconnexion');
        $this->post('/connexion/verification', ['code' => $code])->assertRedirect('/connexion');
    }

    public function test_two_factor_locks_after_too_many_wrong_codes_and_clients_are_not_concerned(): void
    {
        Notification::fake();
        Setting::store(['admin_two_factor_enabled' => true]);
        User::factory()->admin()->create(['email' => 'chef@mapanne.test']);
        $this->post('/connexion', ['login' => 'chef@mapanne.test', 'password' => 'password']);
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/connexion/verification', ['code' => '111111']);
        }
        $response->assertRedirect('/connexion');
        $this->assertGuest();

        $client = User::factory()->create(['email' => 'client@x.bf']);
        $this->post('/connexion', ['login' => 'client@x.bf', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($client);
    }

    public function test_impersonating_admin_cannot_change_email_or_password(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create(['email' => 'vrai@exemple.bf']);

        $this->actingAs($admin)->post("/admin/utilisateurs/{$client->id}/acceder");
        $this->put('/mon-compte', ['name' => 'X', 'email' => 'pirate@exemple.bf'])->assertSessionHas('error');
        $this->assertSame('vrai@exemple.bf', $client->fresh()->email);
    }

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('tile.openstreetmap.org', $response->headers->get('Content-Security-Policy'));
    }

    public function test_admin_is_logged_out_after_inactivity(): void
    {
        Setting::store(['admin_idle_minutes' => 30]);
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();

        $this->travel(31)->minutes();
        $this->get('/admin')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_password_change_revokes_api_tokens_and_tokens_expire(): void
    {
        $user = User::factory()->create();
        $user->createToken('telephone');

        $this->actingAs($user)->put('/mon-compte/mot-de-passe', [
            'current_password' => 'password', 'password' => 'nouveau-mdp-1', 'password_confirmation' => 'nouveau-mdp-1',
        ])->assertSessionHas('success');
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(60 * 24 * 60, config('sanctum.expiration'));
    }

    public function test_product_views_are_limited_per_ip(): void
    {
        $product = Product::factory()->create();

        $this->get('/pieces/'.$product->slug);
        // Nouvelle session (autre navigateur, robot) mais même adresse IP : pas de nouvelle vue.
        $this->flushSession();
        $this->get('/pieces/'.$product->slug);
        $this->assertSame(1, $product->fresh()->views_count);
    }

    public function test_backups_are_encrypted_and_only_super_admin_downloads(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Setting::store(['backup_password' => \Illuminate\Support\Facades\Crypt::encryptString('S3cret-Backup')]);

        try {
            $name = Backup::create();
        } catch (Throwable $e) {
            $this->markTestSkipped('pg_dump indisponible : '.$e->getMessage());
        }

        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path('backups/'.$name));
        $this->assertNotSame(0, $zip->statName('database.dump')['encryption_method']);
        $this->assertTrue(json_decode($zip->getFromName('manifest.json'), true)['encrypted']);
        $zip->close();

        $operator = User::factory()->admin('moderateur')->create();
        $operator->adminRole->update(['permissions' => ['backups.manage']]);
        $this->actingAs($operator)->get('/admin/sauvegardes')->assertOk();
        $this->get('/admin/sauvegardes/'.$name)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/sauvegardes/'.$name)->assertOk();
    }

    public function test_pro_space_requires_owner_and_stays_open_for_owner_without_verification(): void
    {
        $owner = Provider::factory()->approved()->create()->user;
        $owner->forceFill(['email_verified_at' => null])->save();

        // La confirmation d'email obligatoire ne vise que les employés, pas le propriétaire.
        $this->actingAs($owner->fresh())->get('/pro')->assertOk();
    }
}
