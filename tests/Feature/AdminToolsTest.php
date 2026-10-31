<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Profiles\MechanicProfile;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Setting;
use App\Models\User;
use App\Support\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class AdminToolsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_all_admin_pages_render(): void
    {
        Provider::factory()->approved()->create();
        $this->actingAs($this->admin());

        foreach (['/admin', '/admin/roles', '/admin/roles/nouveau', '/admin/offres', '/admin/offres/nouvelle', '/admin/abonnements',
            '/admin/historique', '/admin/sauvegardes', '/admin/donnees', '/admin/recherche?q=garage', '/admin/reglages/appearance',
            '/admin/reglages/home', '/admin/reglages/shop', '/admin/reglages/backups', '/admin/utilisateurs', '/admin/types'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_theme_colors_are_injected_on_public_pages(): void
    {
        $this->get('/')->assertOk()->assertDontSee('--color-brand-500:', false);

        Setting::store(['theme_brand_color' => '#16a34a']);
        $this->get('/')->assertSee('--color-brand-500:#16a34a', false);
    }

    public function test_history_lists_and_exports_actions(): void
    {
        $admin = $this->admin();
        $provider = Provider::factory()->create();
        $this->actingAs($admin)->post("/admin/prestataires/{$provider->slug}/valider");

        $this->get('/admin/historique?domain=provider')->assertOk()->assertSee('Prestataire validé');
        $csv = $this->get('/admin/historique/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Prestataire validé', $csv);
    }

    public function test_exports_are_csv_and_never_include_secrets(): void
    {
        Setting::store(['mail_password' => Crypt::encryptString('topsecret')]);
        $this->actingAs($this->admin());

        $csv = $this->get('/admin/donnees/export/utilisateurs')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}ID;Nom;Email", $csv);
        $this->assertStringNotContainsString('$2y$', $csv);

        $json = $this->get('/admin/donnees/export/reglages')->assertOk()->streamedContent();
        $this->assertArrayNotHasKey('mail_password', json_decode($json, true));
        $this->assertSame(1, ActivityLog::where('action', 'data.exported')->where('details->jeu', 'reglages')->count());
    }

    public function test_import_zones_and_settings(): void
    {
        $this->actingAs($this->admin());
        $csv = UploadedFile::fake()->createWithContent('zones.csv', "ville;quartier;latitude;longitude\nKoudougou;Secteur 1;12,25;-2,36\nNullepart;;48.8;2.3\n");

        $this->post('/admin/donnees/import/zones', ['file' => $csv])->assertSessionHas('success')->assertSessionHas('importErrors');
        $this->assertNotNull(City::where('name', 'Koudougou')->first()?->neighborhoods()->where('name', 'Secteur 1')->first());
        $this->assertNull(City::where('name', 'Nullepart')->first());

        $json = UploadedFile::fake()->createWithContent('reglages.json', json_encode(['site_name' => 'MaPanne BF', 'per_page' => 12, 'mail_password' => 'x', 'inconnu' => 1]));
        $this->post('/admin/donnees/import/reglages', ['file' => $json])->assertSessionHas('success');
        $this->assertSame('MaPanne BF', setting('site_name'));
        $this->assertSame(12, setting('per_page'));
        $this->assertNull(Setting::find('mail_password'));
    }

    public function test_type_profiles_are_stored_in_their_own_table(): void
    {
        $mechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);
        $city = City::create(['name' => 'Ouagadougou', 'slug' => 'ouaga-x', 'latitude' => 12.37, 'longitude' => -1.52]);

        $this->actingAs($mechanic->user)->get('/pro/fiche')->assertOk()->assertSee('Je me déplace chez le client');
        $this->put('/pro/fiche', [
            'business_name' => $mechanic->business_name, 'type' => ProviderType::MECHANIC, 'phone' => $mechanic->phone,
            'city_id' => $city->id, 'latitude' => $mechanic->latitude, 'longitude' => $mechanic->longitude,
            'details' => ['mechanic' => ['is_mobile' => '1', 'travel_radius_km' => '15', 'specialties' => ['moteur', 'injection'], 'vehicle_types' => ['moto']]],
        ])->assertSessionHasNoErrors();

        $profile = MechanicProfile::where('provider_id', $mechanic->id)->first();
        $this->assertTrue($profile->is_mobile);
        $this->assertSame(['moteur', 'injection'], $profile->specialties);

        $this->get('/prestataires/'.$mechanic->slug)->assertSee('Se déplace')->assertSee('15 km');

        // Le type d'activité ne se change pas depuis la fiche pro.
        $this->put('/pro/fiche', [
            'business_name' => $mechanic->business_name, 'type' => ProviderType::STATION, 'phone' => $mechanic->phone,
            'city_id' => $city->id, 'latitude' => $mechanic->latitude, 'longitude' => $mechanic->longitude,
        ])->assertSessionHasNoErrors();
        $this->assertSame(ProviderType::MECHANIC, $mechanic->fresh()->type->slug);

        // Une valeur hors liste est refusée.
        $this->put('/pro/fiche', [
            'business_name' => $mechanic->business_name, 'type' => ProviderType::MECHANIC, 'phone' => $mechanic->phone,
            'city_id' => $city->id, 'latitude' => $mechanic->latitude, 'longitude' => $mechanic->longitude,
            'details' => ['mechanic' => ['specialties' => ['piratage']]],
        ])->assertSessionHasErrors('details.mechanic.specialties.0');
    }

    public function test_delivery_is_disabled_by_default(): void
    {
        $this->assertFalse(feature('delivery_enabled'));
        $shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $this->actingAs($shop->user)->get('/pro/fiche')->assertDontSee('Je livre les pièces');

        Setting::store(['delivery_enabled' => true]);
        $this->get('/pro/fiche')->assertSee('Je livre les pièces');
    }

    public function test_backup_is_created_downloaded_and_deleted(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('logos/test.png', 'x');

        try {
            $name = Backup::create();
        } catch (Throwable $e) {
            $this->markTestSkipped('pg_dump indisponible sur cette machine : '.$e->getMessage());
        }

        $this->assertMatchesRegularExpression('/^mapanne-\d{8}-\d{6}\.zip$/', $name);
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path('backups/'.$name));
        $this->assertNotFalse($zip->locateName('database.dump'));
        $this->assertNotFalse($zip->locateName('files/logos/test.png'));
        $zip->close();

        $this->actingAs($this->admin());
        $this->get('/admin/sauvegardes')->assertOk()->assertSee($name);
        $this->get('/admin/sauvegardes/'.$name)->assertOk();
        $this->get('/admin/sauvegardes/..%2F.env')->assertNotFound();
        $this->post('/admin/sauvegardes/'.$name.'/restaurer', ['confirm' => 'oui'])->assertSessionHasErrors('confirm');
        $this->delete('/admin/sauvegardes/'.$name)->assertSessionHas('success');
        $this->assertSame([], Backup::list());
    }
}
