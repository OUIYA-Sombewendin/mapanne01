<?php

namespace Tests\Feature;

use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderDocument;
use App\Models\ProviderType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Badges de confiance, statistiques des fiches et observatoire des prix. */
class TrustAndStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_sends_a_document_and_admin_grants_the_badge(): void
    {
        Storage::fake('local');
        $garage = Provider::factory()->approved()->create(['type' => ProviderType::GARAGE, 'business_name' => 'Garage Confiance']);

        $this->actingAs($garage->user)->get('/pro/badges')->assertOk()->assertSee('Pièce d\'identité');
        $this->post('/pro/badges', ['kind' => 'identity', 'number' => 'B1234567', 'file' => UploadedFile::fake()->image('cnib.jpg', 800, 500)])
            ->assertSessionHas('success');
        $doc = ProviderDocument::firstOrFail();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertSame('pending', $doc->status);
        $this->get('/prestataires/'.$garage->slug)->assertDontSee('Identité vérifiée');

        // Les documents ne sont visibles que par les admins.
        $this->get("/admin/verifications/{$doc->id}/fichier")->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/verifications')->assertOk()->assertSee('Garage Confiance')->assertSee('B1234567');
        $this->get("/admin/verifications/{$doc->id}/fichier")->assertOk();
        $this->post("/admin/verifications/{$doc->id}", ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->post("/admin/verifications/{$doc->id}", ['decision' => 'approve'])->assertSessionHas('success');

        $this->assertNotNull($garage->fresh()->identity_verified_at);
        $this->get('/prestataires/'.$garage->slug)->assertSee('Identité vérifiée');
        $this->get('/recherche?verified=1')->assertSee('Garage Confiance');
    }

    public function test_rejected_document_keeps_no_badge_and_shows_the_reason(): void
    {
        Storage::fake('local');
        $shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $this->actingAs($shop->user)->post('/pro/badges', ['kind' => 'business', 'file' => UploadedFile::fake()->image('rccm.png')]);
        $doc = ProviderDocument::firstOrFail();

        $this->actingAs(User::factory()->admin()->create())->post("/admin/verifications/{$doc->id}", ['decision' => 'reject', 'reason' => 'Nom différent'])->assertSessionHas('success');
        $this->assertNull($shop->fresh()->business_verified_at);
        $this->actingAs($shop->user)->get('/pro/badges')->assertSee('Nom différent');

        // Un autre prestataire ne peut pas retirer ce document.
        $other = Provider::factory()->approved()->create();
        $this->actingAs($other->user)->delete("/pro/badges/{$doc->id}")->assertNotFound();
    }

    public function test_views_and_contacts_are_counted_once_per_visitor(): void
    {
        $mechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);

        $this->get('/prestataires/'.$mechanic->slug)->assertOk();
        $this->get('/prestataires/'.$mechanic->slug)->assertOk();
        $this->post('/prestataires/'.$mechanic->slug.'/contact', ['channel' => 'whatsapp'])->assertNoContent();
        $this->post('/prestataires/'.$mechanic->slug.'/contact', ['channel' => 'calls'])->assertNoContent();
        $this->post('/prestataires/'.$mechanic->slug.'/contact', ['channel' => 'calls'])->assertNoContent();

        $row = DB::table('provider_stats')->where('provider_id', $mechanic->id)->first();
        $this->assertSame([1, 1, 1], [(int) $row->views, (int) $row->calls, (int) $row->whatsapp]);

        $this->actingAs($mechanic->user)->get('/pro/statistiques')->assertOk()->assertSee('Statistiques de la fiche')->assertSee('Messages WhatsApp');
    }

    public function test_statistics_follow_the_plan_when_subscriptions_are_on(): void
    {
        $mechanic = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);
        Setting::store(['subscriptions_enabled' => true]); // offre gratuite par défaut : sans statistiques

        $this->actingAs($mechanic->user)->get('/pro/statistiques')->assertRedirect('/pro/abonnement');
    }

    public function test_price_watch_shows_ranges_when_enough_shops_sell_a_part(): void
    {
        $category = PartCategory::firstOrCreate(['slug' => 'freinage'], ['name' => 'Freinage']);
        $make = fn (int $price, ?string $name = null) => Product::factory()->create([
            'name' => $name ?? 'Plaquettes de frein Toyota Corolla', 'price' => $price, 'part_category_id' => $category->id, 'condition' => 'new',
        ]);
        $first = $make(6000);
        $make(4500, 'Plaquette frein Toyota Corolla');

        // Deux boutiques seulement : pas de prix constaté.
        $this->get('/pieces/'.$first->slug)->assertOk()->assertDontSee('Prix constaté');

        $make(9000);
        $this->get('/pieces/'.$first->slug)->assertSee('Prix constaté')->assertSee('4 500')->assertSee('9 000');

        $this->actingAs(User::factory()->admin()->create())->get('/admin/observatoire-prix?q=corolla')
            ->assertOk()->assertSee('Freinage')->assertSee('6 000'); // médian
    }
}
