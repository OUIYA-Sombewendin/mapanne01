<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Paiement des abonnements par Orange Money / Moov Money : preuve envoyée, vérifiée à la main par l'admin. */
class MobilePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Provider $shop;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Setting::store(['subscriptions_enabled' => true, 'payment_orange_number' => '+22670000001', 'payment_orange_holder' => 'MaPanne SARL']);
        $this->shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $this->plan = Plan::where('slug', 'standard')->first();
    }

    private function choosePlan(): Subscription
    {
        $this->actingAs($this->shop->user)->post('/pro/abonnement', ['plan_id' => $this->plan->id])
            ->assertRedirect();

        return $this->shop->subscriptions()->where('status', 'pending')->firstOrFail();
    }

    private function proof(array $extra = []): array
    {
        return $extra + [
            'payment_operator' => 'orange',
            'payer_phone' => '76 12 34 56',
            'payment_reference' => 'pp231101.1234.a56789',
            'proof' => UploadedFile::fake()->image('capture.png', 400, 800),
        ];
    }

    public function test_pro_pays_and_sends_the_proof(): void
    {
        $subscription = $this->choosePlan();

        $this->get("/pro/abonnement/{$subscription->id}/paiement")->assertOk()
            ->assertSee('+226 70 00 00 01')->assertSee('MaPanne SARL')->assertDontSee('Moov Money');

        $this->post("/pro/abonnement/{$subscription->id}/paiement", $this->proof())->assertRedirect('/pro/abonnement');

        $subscription->refresh();
        $this->assertSame('pending', $subscription->status);
        $this->assertSame('PP231101.1234.A56789', $subscription->payment_reference);
        $this->assertSame('+22676123456', $subscription->payer_phone);
        $this->assertSame('Orange Money', $subscription->payment_method);
        $this->assertNotNull($subscription->proof_submitted_at);
        Storage::disk('local')->assertExists($subscription->proof_path);

        $this->get('/pro/abonnement')->assertSee('Paiement en cours de vérification');
    }

    public function test_an_operator_without_number_or_a_reused_reference_is_refused(): void
    {
        $subscription = $this->choosePlan();
        $this->post("/pro/abonnement/{$subscription->id}/paiement", $this->proof(['payment_operator' => 'moov']))
            ->assertSessionHasErrors('payment_operator');

        // La même référence déjà utilisée par un autre prestataire.
        $other = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $other->subscriptions()->create(['plan_id' => $this->plan->id, 'status' => 'active', 'amount' => 5000])
            ->forceFill(['payment_operator' => 'orange', 'payment_reference' => 'PP231101.1234.A56789'])->save();

        $this->post("/pro/abonnement/{$subscription->id}/paiement", $this->proof())->assertSessionHasErrors('payment_reference');
        $this->post("/pro/abonnement/{$subscription->id}/paiement", $this->proof(['proof' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'), 'payment_reference' => 'NEW123']))
            ->assertSessionHasErrors('proof');
    }

    public function test_admin_sees_the_proof_and_activates(): void
    {
        $subscription = $this->choosePlan();
        $this->post("/pro/abonnement/{$subscription->id}/paiement", $this->proof());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/abonnements')->assertOk()
            ->assertSee($this->shop->business_name)->assertSee('PP231101.1234.A56789')->assertSee('+226 76 12 34 56');
        $this->get("/admin/abonnements/{$subscription->id}/preuve")->assertOk();

        $this->post("/admin/abonnements/{$subscription->id}/activer", ['amount' => 5000, 'payment_method' => 'Orange Money', 'payment_reference' => 'PP231101.1234.A56789'])
            ->assertSessionHas('success');
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->reviewed_at);
    }

    public function test_admin_rejects_with_a_reason_and_the_pro_sees_it(): void
    {
        $subscription = $this->choosePlan();
        $this->post("/pro/abonnement/{$subscription->id}/paiement", $this->proof());

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/abonnements/{$subscription->id}/refuser", ['reason' => 'Paiement introuvable'])->assertSessionHas('success');
        $this->assertSame('rejected', $subscription->fresh()->status);

        $this->actingAs($this->shop->user)->get('/pro/abonnement')->assertSee('Paiement non validé')->assertSee('Paiement introuvable');
        // La référence d'un paiement refusé peut resservir (ex. erreur de saisie corrigée).
        $this->assertFalse(Subscription::referenceUsed('orange', 'pp231101.1234.a56789'));
    }

    public function test_proofs_are_private(): void
    {
        $subscription = $this->choosePlan();
        $this->post("/pro/abonnement/{$subscription->id}/paiement", $this->proof());

        $this->get("/admin/abonnements/{$subscription->id}/preuve")->assertForbidden();
        $this->post('/deconnexion');
        $this->get("/admin/abonnements/{$subscription->id}/preuve")->assertRedirect('/connexion');

        // Un autre prestataire ne peut pas envoyer de preuve sur cet abonnement.
        $other = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $this->actingAs($other->user)->get("/pro/abonnement/{$subscription->id}/paiement")->assertNotFound();
    }
}
