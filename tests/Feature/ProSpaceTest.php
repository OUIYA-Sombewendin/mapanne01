<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Provider;
use App\Models\Service;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_pages_render(): void
    {
        $this->seed(ServiceSeeder::class);
        $p = Provider::factory()->create();

        foreach (['/pro', '/pro/fiche', '/pro/horaires', '/mon-compte'] as $url) {
            $this->actingAs($p->user)->get($url)->assertOk();
        }
    }

    public function test_pro_updates_profile_services_and_schedule(): void
    {
        $this->seed(ServiceSeeder::class);
        $city = City::create(['name' => 'Ouagadougou', 'slug' => 'ouagadougou', 'latitude' => 12.37, 'longitude' => -1.52]);
        $p = Provider::factory()->create();

        $this->actingAs($p->user)->put('/pro/fiche', [
            'business_name' => 'Garage Renommé',
            'type' => 'garage',
            'phone' => '71 22 33 44',
            'city_id' => $city->id,
            'latitude' => 12.36,
            'longitude' => -1.51,
            'night_service' => '1',
            'services' => Service::take(2)->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('+22671223344', $p->phone);
        $this->assertTrue($p->night_service);
        $this->assertCount(2, $p->services);

        $this->actingAs($p->user)->put('/pro/horaires', ['days' => [
            1 => ['open' => '1', 'opens_at' => '08:00', 'closes_at' => '18:00'],
            5 => ['open' => '1', 'opens_at' => '18:00', 'closes_at' => '02:00'],
            7 => ['open' => '1', 'allday' => '1'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame([1, 5, 7], $p->schedules()->pluck('day')->all());
    }

    public function test_coordinates_outside_burkina_are_rejected(): void
    {
        $p = Provider::factory()->create();

        $this->actingAs($p->user)->put('/pro/fiche', [
            'business_name' => 'X', 'type' => 'garage', 'phone' => '71223344', 'latitude' => 48.85, 'longitude' => 2.35,
        ])->assertSessionHasErrors(['latitude', 'city_id']);
    }
}
