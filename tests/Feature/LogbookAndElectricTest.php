<?php

namespace Tests\Feature;

use App\Models\ChargePoint;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use App\Notifications\MaintenanceDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Carnet d'entretien, page électrique et nouvelle page d'accueil. */
class LogbookAndElectricTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(User $user, array $attrs = []): Vehicle
    {
        return $user->vehicles()->create($attrs + ['type' => 'moto', 'energy' => 'thermique', 'brand' => 'Yamaha', 'model' => 'Crypton']);
    }

    public function test_client_adds_a_vehicle_and_picks_reminders(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)->post('/mon-compte/vehicules', ['type' => 'moto', 'energy' => 'thermique', 'brand' => 'Yamaha'])
            ->assertRedirect();
        $vehicle = $client->vehicles()->firstOrFail();

        // Une moto à essence : vidange proposée, pas de visite technique ni de contrôle de batterie électrique.
        $this->get('/mon-carnet/'.$vehicle->id)->assertOk()->assertSee('Vidange')->assertDontSee('Visite technique')->assertDontSee('Batterie électrique');

        $this->put('/mon-carnet/'.$vehicle->id.'/rappels', ['tasks' => [
            'vidange' => ['on' => 1, 'every' => 2, 'last' => today()->subMonth()->format('Y-m-d')],
            'assurance' => ['on' => 1, 'every' => 12],
            'freins' => ['on' => 0],
        ]])->assertSessionHas('success');

        $reminders = $vehicle->reminders()->get()->keyBy('task');
        $this->assertSame(['assurance', 'vidange'], $reminders->keys()->sort()->values()->all());
        $this->assertTrue($reminders['vidange']->next_due_on->isSameDay(today()->subMonth()->addMonthsNoOverflow(2)));
        $this->assertTrue($reminders['assurance']->next_due_on->isSameDay(today()->addMonthsNoOverflow(12)));
    }

    public function test_logging_a_service_pushes_the_reminder_back(): void
    {
        $client = User::factory()->create();
        $vehicle = $this->vehicle($client);
        $vehicle->reminders()->create(['task' => 'vidange', 'label' => 'Vidange', 'every_months' => 2, 'next_due_on' => today()->subDays(3), 'notified_for' => today()->subDays(3)]);

        $this->actingAs($client)->post('/mon-carnet/'.$vehicle->id.'/entretiens', [
            'task' => 'vidange', 'done_on' => today()->format('Y-m-d'), 'cost' => '3 500', 'provider_name' => 'Garage du coin',
        ])->assertSessionHas('success');

        $log = $vehicle->logs()->firstOrFail();
        $this->assertSame(['Vidange', 3500, 'Garage du coin'], [$log->label, $log->cost, $log->provider_name]);
        $reminder = $vehicle->reminders()->first();
        $this->assertTrue($reminder->next_due_on->isSameDay(today()->addMonthsNoOverflow(2)));
        $this->assertNull($reminder->notified_for);

        // Autre réparation : une description est obligatoire.
        $this->post('/mon-carnet/'.$vehicle->id.'/entretiens', ['done_on' => today()->format('Y-m-d')])->assertSessionHasErrors('label');

        $this->get('/mon-carnet/'.$vehicle->id)->assertSee('Garage du coin')->assertSee('3 500 F');
    }

    public function test_logbook_is_private(): void
    {
        $owner = User::factory()->create();
        $vehicle = $this->vehicle($owner);
        $log = $vehicle->logs()->create(['done_on' => today(), 'label' => 'Vidange']);

        $other = User::factory()->create();
        $this->actingAs($other)->get('/mon-carnet/'.$vehicle->id)->assertNotFound();
        $this->post('/mon-carnet/'.$vehicle->id.'/entretiens', ['task' => 'vidange', 'done_on' => today()->format('Y-m-d')])->assertNotFound();
        $this->delete('/mon-carnet/'.$vehicle->id.'/entretiens/'.$log->id)->assertNotFound();
        $this->assertSame(1, $vehicle->logs()->count());
    }

    public function test_reminders_are_sent_once_per_due_date(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $vehicle = $this->vehicle($client);
        $vehicle->reminders()->create(['task' => 'vidange', 'label' => 'Vidange', 'every_months' => 2, 'next_due_on' => today()->addDays(3)]);
        $vehicle->reminders()->create(['task' => 'assurance', 'label' => 'Assurance', 'every_months' => 12, 'next_due_on' => today()->addMonths(5)]);

        $this->artisan('mapanne:maintenance-reminders')->assertSuccessful();
        Notification::assertSentTo($client, MaintenanceDue::class, fn ($n) => $n->reminders->pluck('task')->all() === ['vidange']);

        $this->artisan('mapanne:maintenance-reminders');
        Notification::assertSentToTimes($client, MaintenanceDue::class, 1);
        $this->assertTrue(VehicleReminder::where('task', 'vidange')->first()->notified_for->isSameDay(today()->addDays(3)));
    }

    public function test_switching_to_electric_drops_fuel_only_reminders(): void
    {
        $client = User::factory()->create();
        $vehicle = $this->vehicle($client);
        $vehicle->reminders()->create(['task' => 'vidange', 'label' => 'Vidange', 'every_months' => 2, 'next_due_on' => today()->addMonth()]);
        $vehicle->reminders()->create(['task' => 'freins', 'label' => 'Freins', 'every_months' => 6, 'next_due_on' => today()->addMonth()]);

        $this->actingAs($client)->put('/mon-compte/vehicules/'.$vehicle->id, ['type' => 'moto', 'energy' => 'electrique', 'brand' => 'Yamaha'])->assertSessionHas('success');

        $this->assertSame(['freins'], $vehicle->reminders()->pluck('task')->all());
        $this->get('/mon-carnet/'.$vehicle->id)->assertSee('Batterie électrique et câbles')->assertSee('points de recharge');
    }

    public function test_electric_page_lists_verified_specialists_and_charge_points(): void
    {
        $specialist = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC, 'business_name' => 'Atelier Volt', 'ev_verified_at' => now()]);
        Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC, 'business_name' => 'Garage Essence']);
        ChargePoint::create(['name' => 'Échange Ouaga 2000', 'kind' => 'swap', 'latitude' => 12.33, 'longitude' => -1.5, 'vehicles' => ['moto']]);
        ChargePoint::create(['name' => 'Point masqué', 'kind' => 'recharge', 'latitude' => 12.3, 'longitude' => -1.5, 'is_active' => false]);

        $this->get('/electrique')->assertOk()
            ->assertSee('Atelier Volt')->assertDontSee('Garage Essence')
            ->assertSee('Échange Ouaga 2000')->assertDontSee('Point masqué');
        $this->get('/prestataires/'.$specialist->slug)->assertSee('Spécialiste électrique');
        $this->get('/recherche?electric=1')->assertSee('Atelier Volt')->assertDontSee('Garage Essence');
    }

    public function test_admin_manages_charge_points(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/points-electriques')->assertOk();

        $this->post('/admin/points-electriques', ['name' => 'Recharge Gounghin', 'kind' => 'recharge'])->assertSessionHasErrors('latitude');
        $this->post('/admin/points-electriques', [
            'name' => 'Recharge Gounghin', 'kind' => 'recharge', 'latitude' => 12.36, 'longitude' => -1.53, 'vehicles' => ['moto', 'voiture'], 'is_active' => 1,
        ])->assertRedirect('/admin/points-electriques');

        $point = ChargePoint::firstOrFail();
        $this->assertSame(['moto', 'voiture'], $point->vehicles);
        $this->delete('/admin/points-electriques/'.$point->id)->assertSessionHas('success');
        $this->assertSame(0, ChargePoint::count());

        $this->actingAs(User::factory()->create())->get('/admin/points-electriques')->assertForbidden();
    }

    public function test_home_page_offers_breakdown_by_vehicle_and_electric_band(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Je suis en panne')
            ->assertSee('Moto ou voiture électrique')
            ->assertSee('Ne ratez plus une vidange')
            ->assertDontSee('Comment ça marche');

        // Le lien de la page d'accueil pré-remplit la demande de dépannage.
        $this->get('/depannage?vehicle=moto&energy=electrique&problem=electrique')->assertOk()->assertSee('energy\u0022:\u0022electrique', false);
    }
}
