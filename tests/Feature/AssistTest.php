<?php

namespace Tests\Feature;

use App\Models\AssistRequest;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AssistRequested;
use App\Notifications\AssistUpdate;
use App\Notifications\Channels\SmsChannel;
use App\Support\Channels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Dépannage en direct : demande du conducteur, prestataires prévenus, acceptation, suivi. */
class AssistTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 12.3714;

    private const LNG = -1.5197;

    private function request(array $extra = []): array
    {
        return $extra + [
            'vehicle_type' => 'moto', 'energy' => 'thermique', 'problem' => 'crevaison',
            'latitude' => self::LAT, 'longitude' => self::LNG,
            'contact_name' => 'Awa', 'contact_phone' => '70 11 22 33',
        ];
    }

    private function pro(string $type, float $offset = 0.01): Provider
    {
        return Provider::factory()->approved()->at(self::LAT + $offset, self::LNG)->create(['type' => $type]);
    }

    public function test_driver_without_account_sends_a_request_to_nearby_trades(): void
    {
        Notification::fake();
        Setting::store(['sms_enabled' => true, 'sms_driver' => 'log']);
        $mechanic = $this->pro(ProviderType::MECHANIC);
        $tow = $this->pro(ProviderType::TOW_TRUCK);
        $station = $this->pro(ProviderType::STATION);
        $far = $this->pro(ProviderType::MECHANIC, 1.5); // ~165 km

        $response = $this->post('/depannage', $this->request());
        $assist = AssistRequest::firstOrFail();
        $response->assertRedirect('/depannage/'.$assist->token);

        $this->assertSame('open', $assist->status);
        $this->assertSame('+22670112233', $assist->contact_phone);
        $this->assertSame(2, $assist->notified_count);
        Notification::assertSentTo($mechanic->user, AssistRequested::class, fn ($n, $channels) => $channels === [SmsChannel::class]);
        Notification::assertSentTo($tow->user, AssistRequested::class);
        Notification::assertNotSentTo($station->user, AssistRequested::class);
        Notification::assertNotSentTo($far->user, AssistRequested::class);

        $this->get('/depannage/'.$assist->token)->assertOk()->assertSee('En attente d\'un prestataire');
        // Une seule demande en cours à la fois.
        $this->post('/depannage', $this->request())->assertRedirect('/depannage/'.$assist->token);
        $this->assertSame(1, AssistRequest::count());
    }

    public function test_towing_only_reaches_tow_trucks_and_trades_need_the_module(): void
    {
        $mechanic = $this->pro(ProviderType::MECHANIC);
        $tow = $this->pro(ProviderType::TOW_TRUCK);

        $this->post('/depannage', $this->request(['problem' => 'remorquage']));
        $this->assertSame([$tow->id], AssistRequest::first()->notifiedProviders->pluck('id')->all());

        ProviderType::bySlug(ProviderType::TOW_TRUCK)->update(['modules' => ['services']]);
        $this->flushSession();
        $this->post('/depannage', $this->request(['problem' => 'crevaison', 'contact_phone' => '70 99 88 77']));
        $this->assertSame([$mechanic->id], AssistRequest::latest('id')->first()->notifiedProviders->pluck('id')->all());
    }

    public function test_first_provider_to_accept_wins_and_driver_follows_progress(): void
    {
        Notification::fake();
        Setting::store(['sms_enabled' => true, 'sms_driver' => 'log']);
        $first = $this->pro(ProviderType::MECHANIC);
        $second = $this->pro(ProviderType::TOW_TRUCK, 0.02);
        $stranger = $this->pro(ProviderType::MECHANIC, 1.5);
        $this->post('/depannage', $this->request());
        $assist = AssistRequest::firstOrFail();
        $this->flushSession(); // le message du conducteur contient son numéro

        // Le numéro n'est pas montré avant l'acceptation.
        $this->actingAs($first->user)->get('/pro/depannages')->assertOk()->assertSee('Crevaison')->assertDontSee('70 11 22 33');
        $this->actingAs($stranger->user)->post("/pro/depannages/{$assist->token}/accepter")->assertForbidden();

        $this->actingAs($first->user)->post("/pro/depannages/{$assist->token}/accepter")->assertSessionHas('success');
        $this->actingAs($second->user)->post("/pro/depannages/{$assist->token}/accepter")->assertSessionHas('error');
        $this->assertSame($first->id, $assist->fresh()->provider_id);
        Notification::assertSentTo(new AnonymousNotifiable, AssistUpdate::class);

        $this->actingAs($first->user)->get('/pro/depannages')->assertSee('70 11 22 33');
        $this->post("/pro/depannages/{$assist->token}/etape", ['step' => 'on_way']);
        $this->assertSame('on_way', $assist->fresh()->status);
        $this->getJson('/depannage/'.$assist->token.'/etat')->assertJson(['status' => 'on_way', 'provider' => ['name' => $first->business_name]]);

        $this->post("/pro/depannages/{$assist->token}/etape", ['step' => 'done']);
        $this->assertSame('done', $assist->fresh()->status);
        $this->actingAs($second->user)->post("/pro/depannages/{$assist->token}/etape", ['step' => 'done'])->assertForbidden();
    }

    public function test_provider_can_hand_a_request_back(): void
    {
        $first = $this->pro(ProviderType::MECHANIC);
        $second = $this->pro(ProviderType::TOW_TRUCK, 0.02);
        $this->post('/depannage', $this->request());
        $assist = AssistRequest::firstOrFail();

        $this->actingAs($first->user)->post("/pro/depannages/{$assist->token}/accepter");
        $this->post("/pro/depannages/{$assist->token}/etape", ['step' => 'release']);
        $this->assertSame('open', $assist->fresh()->status);
        // Celui qui a rendu la demande ne la voit plus ; l'autre peut la prendre.
        $this->get('/pro/depannages')->assertDontSee('J\'accepte');
        $this->actingAs($second->user)->post("/pro/depannages/{$assist->token}/accepter")->assertSessionHas('success');
    }

    public function test_only_the_driver_can_cancel_and_stale_requests_expire(): void
    {
        $this->pro(ProviderType::MECHANIC);
        $this->post('/depannage', $this->request());
        $assist = AssistRequest::firstOrFail();

        $this->flushSession();
        $this->post("/depannage/{$assist->token}/annuler")->assertForbidden();
        $this->withSession(['assist_tokens' => [$assist->token]])->post("/depannage/{$assist->token}/annuler")->assertRedirect();
        $this->assertSame('cancelled', $assist->fresh()->status);

        $old = AssistRequest::create($this->request(['contact_phone' => '+22670000001']));
        $old->forceFill(['created_at' => now()->subHours(2)])->save();
        $this->artisan('mapanne:assist-expire')->assertSuccessful();
        $this->assertSame('expired', $old->fresh()->status);
    }

    public function test_position_falls_back_to_the_city_and_guests_can_be_refused(): void
    {
        $city = \App\Models\City::create(['name' => 'Ouagadougou', 'slug' => 'ouaga-a', 'latitude' => self::LAT, 'longitude' => self::LNG]);
        $this->post('/depannage', $this->request(['latitude' => null, 'longitude' => null]))->assertSessionHasErrors('latitude');
        $this->post('/depannage', $this->request(['latitude' => null, 'longitude' => null, 'city_id' => $city->id]))->assertRedirect();
        $this->assertEqualsWithDelta(self::LAT, AssistRequest::first()->latitude, 0.0001);

        Setting::store(['assist_guests_allowed' => false]);
        $this->flushSession();
        $this->get('/depannage')->assertRedirect('/connexion');
        $this->post('/depannage', $this->request(['contact_phone' => '70 55 55 55']))->assertForbidden();

        Setting::store(['assist_enabled' => false]);
        $this->get('/depannage')->assertNotFound();
    }

    public function test_admin_follows_and_closes_requests(): void
    {
        $this->pro(ProviderType::MECHANIC);
        $this->post('/depannage', $this->request());
        $assist = AssistRequest::firstOrFail();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/depannages')->assertOk()->assertSee('Awa')->assertSee('Crevaison');
        $this->post("/admin/depannages/{$assist->token}/fermer", ['reason' => 'Appel abusif'])->assertSessionHas('success');
        $this->assertSame('cancelled', $assist->fresh()->status);
    }

    public function test_paid_channels_are_off_by_default_and_push_subscriptions_are_saved(): void
    {
        $user = User::factory()->create(['phone' => '+22670123456']);
        $this->assertSame([], Channels::for($user));
        $this->assertFalse(Channels::smsReady());
        $this->assertFalse(Channels::whatsappReady());

        $this->actingAs($user)->postJson('/notifications/push', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'secret'],
        ])->assertOk();
        $this->assertSame(1, $user->pushSubscriptions()->count());
        $this->postJson('/notifications/push', ['endpoint' => 'http://insecure.test/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])->assertUnprocessable();
    }
}
