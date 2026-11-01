<?php

namespace Tests\Feature;

use App\Enums\ProviderStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_registers_with_email_and_optional_phone(): void
    {
        Notification::fake();

        $this->post('/inscription', [
            'account_type' => 'client',
            'name' => 'Awa Kaboré',
            'email' => 'Awa@Exemple.BF',
            'phone' => '70 11 22 33',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
            'terms' => '1',
        ])->assertRedirect('/mon-espace');

        $user = User::firstWhere('email', 'awa@exemple.bf');
        $this->assertSame('+22670112233', $user->phone);
        $this->assertSame(UserRole::Client, $user->role);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);

        // Le téléphone est facultatif pour un client.
        $this->post('/deconnexion');
        $this->post('/inscription', [
            'account_type' => 'client', 'name' => 'Sans Tel', 'email' => 'sanstel@exemple.bf',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse', 'terms' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertNull(User::firstWhere('email', 'sanstel@exemple.bf')->phone);
    }

    public function test_email_is_required_and_phone_required_for_pros(): void
    {
        $this->post('/inscription', ['account_type' => 'client', 'name' => 'X', 'password' => 'motdepasse', 'password_confirmation' => 'motdepasse', 'terms' => '1'])
            ->assertSessionHasErrors('email');
        $this->post('/inscription', ['account_type' => 'pro', 'provider_type' => 'garage', 'business_name' => 'G', 'name' => 'X', 'email' => 'x@exemple.bf', 'password' => 'motdepasse', 'password_confirmation' => 'motdepasse', 'terms' => '1'])
            ->assertSessionHasErrors('phone');
    }

    public function test_professional_registration_creates_pending_provider(): void
    {
        $city = City::create(['name' => 'Ouagadougou', 'slug' => 'ouagadougou', 'latitude' => 12.37, 'longitude' => -1.52]);

        $this->post('/inscription', [
            'account_type' => 'pro',
            'provider_type' => 'garage',
            'business_name' => 'Garage Test',
            'name' => 'Issa Ouédraogo',
            'email' => 'issa@exemple.bf',
            'phone' => '+226 76 00 00 01',
            'city_id' => $city->id,
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
            'terms' => '1',
        ])->assertRedirect('/pro');

        $user = User::firstWhere('phone', '+22676000001');
        $this->assertSame(UserRole::Provider, $user->role);
        $this->assertSame(ProviderStatus::Pending, $user->provider->status);
    }

    public function test_registration_form_adapts_to_each_trade(): void
    {
        $this->get('/inscription?type=pro')
            ->assertOk()
            ->assertSee('Votre métier')
            ->assertSee('details[garage][has_diagnostic]', false)
            ->assertSee('details[station][fuel_types][]', false)
            ->assertSee('details[shop][sells_used]', false);
    }

    public function test_trade_answers_given_at_registration_fill_the_trade_profile(): void
    {
        $city = City::create(['name' => 'Ouagadougou', 'slug' => 'ouagadougou', 'latitude' => 12.37, 'longitude' => -1.52]);

        $this->post('/inscription', [
            'account_type' => 'pro',
            'provider_type' => 'garage',
            'business_name' => 'Garage Wend-Kuuni',
            'name' => 'Issa Ouédraogo',
            'email' => 'garage@exemple.bf',
            'phone' => '+226 76 00 00 02',
            'city_id' => $city->id,
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
            'terms' => '1',
            'details' => [
                'garage' => ['employees' => '4', 'has_diagnostic' => '1'],
                // Les réponses d'un autre métier sont ignorées.
                'station' => ['brand' => 'Oryx'],
            ],
        ])->assertRedirect('/pro');

        $provider = User::firstWhere('email', 'garage@exemple.bf')->provider;
        $this->assertSame(4, $provider->garageProfile->employees);
        $this->assertTrue($provider->garageProfile->has_diagnostic);
        $this->assertFalse($provider->garageProfile->has_paint_shop);
        $this->assertNull($provider->stationProfile);

        // Une valeur invalide est refusée.
        auth()->logout();
        $this->post('/inscription', [
            'account_type' => 'pro', 'provider_type' => 'station', 'business_name' => 'Station', 'name' => 'A',
            'email' => 'station@exemple.bf', 'phone' => '+226 76 00 00 03', 'city_id' => $city->id,
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse', 'terms' => '1',
            'details' => ['station' => ['fuel_types' => ['kerosene']]],
        ])->assertSessionHasErrors('details.station.fuel_types.0');
    }

    public function test_role_cannot_be_injected_at_registration(): void
    {
        $this->post('/inscription', [
            'account_type' => 'client',
            'role' => 'admin',
            'is_active' => '1',
            'name' => 'Pirate',
            'email' => 'pirate@exemple.bf',
            'phone' => '70000099',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
            'terms' => '1',
        ]);

        $this->assertSame(UserRole::Client, User::firstWhere('phone', '+22670000099')->role);
    }

    public function test_login_with_email_or_phone_and_suspended_account_is_refused(): void
    {
        $user = User::factory()->create(['phone' => '+22670123456', 'email' => 'moi@exemple.bf']);

        $this->post('/connexion', ['login' => 'MOI@exemple.bf', 'password' => 'password'])->assertRedirect('/mon-espace');
        $this->post('/deconnexion');
        $this->post('/connexion', ['login' => '70 12 34 56', 'password' => 'password'])->assertRedirect('/mon-espace');
        $this->post('/deconnexion');

        // L'admin peut désactiver la connexion par téléphone.
        Setting::store(['login_with_phone_enabled' => false]);
        $this->post('/connexion', ['login' => '70123456', 'password' => 'password'])->assertSessionHasErrors('login');
        Setting::store(['login_with_phone_enabled' => true]);

        $user->forceFill(['is_active' => false])->save();
        $this->post('/connexion', ['login' => '70123456', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_roles_are_separated(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)->get('/admin')->assertForbidden();
        $this->actingAs($client)->get('/pro')->assertForbidden();
        $this->get('/mon-compte')->assertOk();
    }

    public function test_api_token_login(): void
    {
        User::factory()->create(['phone' => '+22670123456']);

        $token = $this->postJson('/api/v1/auth/login', ['login' => '70123456', 'password' => 'password', 'device_name' => 'test'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.phone', '+22670123456');
    }
}
