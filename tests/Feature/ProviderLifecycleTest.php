<?php

namespace Tests\Feature;

use App\Enums\ProviderStatus;
use App\Models\City;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\User;
use App\Notifications\ProviderChangesReviewed;
use App\Support\MapLink;
use App\Support\TradeModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Stations, statistiques de visite, localisation, statut de la fiche et champs verrouillés après validation. */
class ProviderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_space_has_no_sales_management(): void
    {
        $this->assertNotContains('invoices', TradeModules::defaults('station'));

        $station = Provider::factory()->approved()->create(['type' => ProviderType::STATION]);
        $station->type->forceFill(['modules' => TradeModules::defaults('station')])->save();
        ProviderType::flushCache();

        $this->actingAs($station->user)->get('/pro')->assertOk()->assertDontSee('Nouvelle vente')->assertSee('Vues de la fiche (7 jours)');
    }

    public function test_visits_are_counted_with_their_source_hour_and_directions(): void
    {
        $provider = Provider::factory()->approved()->create();

        $this->withHeader('referer', url('/recherche?q=pneu'))->get('/prestataires/'.$provider->slug)->assertOk();
        $this->post('/prestataires/'.$provider->slug.'/contact', ['channel' => 'directions'])->assertNoContent();

        $row = DB::table('provider_stats')->where('provider_id', $provider->id)->first();
        $this->assertSame([1, 1, 1], [(int) $row->views, (int) $row->src_search, (int) $row->directions]);
        $this->assertSame(1, (int) DB::table('provider_stat_hours')->where('provider_id', $provider->id)->where('hour', now()->hour)->value('views'));

        $this->actingAs($provider->user)->get('/pro/statistiques')->assertOk()
            ->assertSee('Itinéraires demandés')->assertSee('Heures de visite')->assertSee('Liste de résultats');
    }

    public function test_map_links_and_place_search(): void
    {
        $this->assertSame(['lat' => 12.3714, 'lng' => -1.5197], MapLink::parse('https://www.google.com/maps/place/X/@12.3714,-1.5197,17z'));
        $this->assertSame(['lat' => 12.36, 'lng' => -1.53], MapLink::parse('https://maps.google.com/maps?q=12.36,-1.53'));
        $this->assertSame(['lat' => 12.36, 'lng' => -1.53], MapLink::parse('12.36, -1.53'));
        $this->assertNull(MapLink::parse('Ouaga 2000'));

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['display_name' => 'Pharmacie du Progrès, Ouagadougou', 'lat' => '12.37', 'lon' => '-1.52']]),
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Y/@12.35,-1.5,16z']),
        ]);
        $pro = Provider::factory()->create();
        $this->actingAs($pro->user)->getJson('/localisation/chercher?q=pharmacie progrès')->assertOk()->assertJsonPath('0.lat', 12.37);
        $this->postJson('/localisation/lien', ['link' => 'https://maps.app.goo.gl/abc123'])->assertOk()->assertJson(['lat' => 12.35, 'lng' => -1.5]);
        // Un lien court vers un autre site n'est jamais suivi.
        $this->postJson('/localisation/lien', ['link' => 'https://exemple.com/redirect'])->assertStatus(422);
    }

    public function test_pending_provider_is_hidden_and_sees_its_status(): void
    {
        $provider = Provider::factory()->create(['status' => ProviderStatus::Pending]);
        $this->get('/prestataires/'.$provider->slug)->assertNotFound();

        $this->actingAs($provider->user)->get('/pro/horaires')->assertSee('En attente de validation');
    }

    public function test_approval_appears_in_the_pro_bell(): void
    {
        $provider = Provider::factory()->create(['status' => ProviderStatus::Pending]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/prestataires/'.$provider->slug.'/valider')->assertRedirect();
        $this->assertSame(1, $provider->user->unreadNotifications()->count());

        $this->actingAs($provider->user->fresh())->get('/pro')->assertSee('Fiche validée : vous êtes en ligne')->assertSee('Statut : <strong>Approuvée</strong>', false);
        $id = $provider->user->notifications()->first()->id;
        $this->get('/notifications/'.$id)->assertRedirect('/pro');
        $this->assertSame(0, $provider->user->unreadNotifications()->count());
    }

    public function test_approved_profile_locks_verified_fields_until_admin_applies(): void
    {
        Notification::fake();
        $provider = Provider::factory()->approved()->create(['business_name' => 'Garage Sankara', 'city_id' => null]);
        $city = City::create(['name' => 'Ouagadougou', 'slug' => 'ouaga', 'latitude' => 12.37, 'longitude' => -1.52]);
        $payload = fn (array $over = []) => $over + [
            'business_name' => $provider->business_name, 'phone' => $provider->phone,
            'city_id' => $city->id, 'latitude' => $provider->latitude, 'longitude' => $provider->longitude,
        ];

        $this->actingAs($provider->user)->get('/pro/fiche')->assertSee('demander un changement');
        $this->put('/pro/fiche', $payload(['business_name' => 'Garage Wend Kuni', 'description' => 'Nouveau texte']))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'demande de modification'));

        $fresh = $provider->fresh();
        $this->assertSame(['Garage Sankara', 'Nouveau texte'], [$fresh->business_name, $fresh->description]); // nom inchangé, le reste enregistré
        $this->assertSame(['business_name' => 'Garage Wend Kuni'], $fresh->requestedChanges());
        $this->get('/pro/horaires')->assertSee('demande de modification de la fiche est en cours');

        // L'admin applique : le nom change et le prestataire est prévenu.
        $this->actingAs(User::factory()->admin()->create())->post('/admin/prestataires/'.$provider->slug.'/appliquer')->assertSessionHas('success');
        $fresh = $provider->fresh();
        $this->assertSame('Garage Wend Kuni', $fresh->business_name);
        $this->assertFalse($fresh->needs_review);
        Notification::assertSentTo($provider->user, ProviderChangesReviewed::class, fn ($n) => $n->applied);

        // Une demande refusée ne change rien.
        $this->actingAs($provider->user)->put('/pro/fiche', $payload(['business_name' => 'Garage Wend Kuni', 'phone' => '71999999']));
        $this->actingAs(User::factory()->admin()->create())->post('/admin/prestataires/'.$provider->slug.'/verifie', ['reason' => 'Numéro injoignable'])->assertSessionHas('success');
        $this->assertSame($provider->phone, $provider->fresh()->phone);
        Notification::assertSentTo($provider->user, ProviderChangesReviewed::class, fn ($n) => ! $n->applied && $n->reason === 'Numéro injoignable');
    }
}
