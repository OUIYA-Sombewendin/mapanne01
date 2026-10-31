<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_geo_search_orders_by_distance_and_respects_radius(): void
    {
        // Centre-ville de Ouagadougou ; ~1 km, ~4 km et ~30 km (Ziniaré).
        Provider::factory()->approved()->at(12.3800, -1.5197)->create(['business_name' => 'Proche']);
        Provider::factory()->approved()->at(12.4074, -1.5197)->create(['business_name' => 'Moyen']);
        Provider::factory()->approved()->at(12.5833, -1.3000)->create(['business_name' => 'Loin']);

        $names = collect($this->getJson('/api/v1/providers?lat=12.3714&lng=-1.5197&radius=10')->json('data'))->pluck('name');

        $this->assertSame(['Proche', 'Moyen'], $names->all());
    }

    public function test_open_now_handles_overnight_schedules(): void
    {
        $night = Provider::factory()->approved()->create(['business_name' => 'Nuit']);
        // Vendredi 18:00 → 02:00
        $night->schedules()->create(['day' => 5, 'opens_at' => '18:00', 'closes_at' => '02:00']);
        $day = Provider::factory()->approved()->create(['business_name' => 'Jour']);
        $day->schedules()->create(['day' => 6, 'opens_at' => '08:00', 'closes_at' => '18:00']);

        // Samedi 1h du matin : la plage de nuit de vendredi est encore ouverte.
        $saturdayNight = Carbon::parse('2026-10-31 01:00');
        $this->assertSame(['Nuit'], Provider::visible()->openAt($saturdayNight)->pluck('business_name')->all());
        $this->assertTrue($night->load('schedules')->isOpenAt($saturdayNight));
        $this->assertFalse($day->load('schedules')->isOpenAt($saturdayNight));

        $saturdayNoon = Carbon::parse('2026-10-31 12:00');
        $this->assertSame(['Jour'], Provider::visible()->openAt($saturdayNoon)->pluck('business_name')->all());
    }

    public function test_one_review_per_client_and_rating_is_recomputed(): void
    {
        $p = Provider::factory()->approved()->create();
        $client = User::factory()->create();

        $this->actingAs($client)->post("/prestataires/{$p->slug}/avis", ['rating' => 2]);
        $this->actingAs($client)->post("/prestataires/{$p->slug}/avis", ['rating' => 5, 'comment' => 'Finalement top']);

        $p->refresh();
        $this->assertSame(1, $p->reviews_count);
        $this->assertSame(5.0, $p->rating_avg);
    }

    public function test_providers_cannot_review_and_hidden_reviews_do_not_count(): void
    {
        $p = Provider::factory()->approved()->create();
        $competitor = Provider::factory()->approved()->create();

        $this->actingAs($competitor->user)->post("/prestataires/{$p->slug}/avis", ['rating' => 1])->assertForbidden();

        $client = User::factory()->create();
        $this->actingAs($client)->post("/prestataires/{$p->slug}/avis", ['rating' => 1]);
        $review = $p->reviews()->first();

        $this->actingAs(User::factory()->admin()->create())->post("/admin/avis/{$review->id}/visibilite");

        $p->refresh();
        $this->assertSame(0, $p->reviews_count);
    }

    public function test_cannot_review_pending_provider(): void
    {
        $p = Provider::factory()->create();

        $this->actingAs(User::factory()->create())->post("/prestataires/{$p->slug}/avis", ['rating' => 5])->assertForbidden();
    }
}
