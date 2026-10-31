<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_photos_are_resized_and_reencoded_without_metadata(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('photo.jpg', 3000, 2000);

        $path = ImageOptimizer::store($file, 'photos');

        $this->assertStringEndsWith('.webp', $path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(1600, $w);
        $this->assertSame(1067, $h);
        $this->assertStringNotContainsString('Exif', Storage::disk('public')->get($path));

        // Un fichier qui n'est pas une image lisible est conservé tel quel.
        $raw = ImageOptimizer::store(UploadedFile::fake()->create('scan.jpg', 10), 'photos');
        $this->assertTrue(Storage::disk('public')->exists($raw));
    }

    public function test_pro_photo_upload_goes_through_the_optimizer(): void
    {
        Storage::fake('public');
        $provider = Provider::factory()->approved()->create();

        $this->actingAs($provider->user)->post('/pro/photos', ['photos' => [UploadedFile::fake()->image('a.jpg', 2400, 1800)]])->assertSessionHasNoErrors();
        $this->assertStringEndsWith('.webp', $provider->photos()->first()->path);
    }

    public function test_public_page_highlights_what_matters_for_each_trade(): void
    {
        $station = Provider::factory()->approved()->create(['type' => ProviderType::STATION]);
        $station->saveDetails(['brand' => 'Oryx', 'fuel_types' => ['super', 'gasoil'], 'has_car_wash' => true]);
        $this->get('/prestataires/'.$station->slug)->assertSee('Carburants')->assertSee('Gasoil')->assertSee('Oryx')->assertSee('Lavage');

        $tow = Provider::factory()->approved()->create(['type' => ProviderType::TOW_TRUCK]);
        $tow->saveDetails(['base_price' => 15000, 'price_per_km' => 500, 'coverage_km' => 60, 'trucks_count' => 2]);
        $this->get('/prestataires/'.$tow->slug)->assertSee('Appeler le dépanneur')->assertSee('15 000 F')->assertSee('500 F')->assertSee('60 km');

        $mechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);
        $mechanic->saveDetails(['is_mobile' => true, 'travel_radius_km' => 15]);
        $this->get('/prestataires/'.$mechanic->slug)->assertSee('Se déplace')->assertSee('15 km')->assertDontSee('Carburants');
    }

    public function test_admin_edit_form_follows_the_trade_and_type_change_drops_old_services(): void
    {
        $repair = ServiceCategory::create(['name' => 'Réparation', 'slug' => 'reparation']);
        $station = ServiceCategory::create(['name' => 'Station', 'slug' => 'station']);
        $brakes = Service::create(['service_category_id' => $repair->id, 'name' => 'Freinage', 'slug' => 'freinage-y', 'is_active' => true]);
        $fuel = Service::create(['service_category_id' => $station->id, 'name' => 'Super 91', 'slug' => 'super-y', 'is_active' => true]);
        $city = City::create(['name' => 'Ouagadougou', 'slug' => 'ouaga-y', 'latitude' => 12.37, 'longitude' => -1.52]);

        $garage = Provider::factory()->approved()->create(['type' => ProviderType::GARAGE, 'city_id' => $city->id]);
        $garage->services()->sync([$brakes->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/admin/prestataires/{$garage->slug}/modifier")->assertOk()
            ->assertSee('Nom du garage')->assertSee('Pont élévateur')->assertSee('Freinage')->assertDontSee('Super 91');

        $base = ['business_name' => $garage->business_name, 'phone' => $garage->phone, 'city_id' => $city->id, 'latitude' => $garage->latitude, 'longitude' => $garage->longitude, 'services_present' => '1'];

        // Un service d'un autre métier est ignoré.
        $this->put("/admin/prestataires/{$garage->slug}", $base + ['type' => ProviderType::GARAGE, 'services' => [$brakes->id, $fuel->id]])->assertSessionHasNoErrors();
        $this->assertSame([$brakes->id], $garage->services()->pluck('services.id')->all());

        // Changement de métier : services de l'ancien métier retirés, action tracée.
        $this->put("/admin/prestataires/{$garage->slug}", $base + ['type' => ProviderType::STATION, 'services' => [$brakes->id]])->assertSessionHasNoErrors();
        $this->assertSame(ProviderType::STATION, $garage->fresh()->type->slug);
        $this->assertSame([], $garage->services()->pluck('services.id')->all());
        $this->assertSame(1, ActivityLog::where('action', 'provider.type_changed')->count());
    }
}
