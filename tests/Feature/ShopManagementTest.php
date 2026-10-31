<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Support\ShopReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopManagementTest extends TestCase
{
    use RefreshDatabase;

    private Provider $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        $this->product = Product::factory()->for($this->shop, 'provider')->create(['stock' => 10, 'price' => 5000, 'cost_price' => 3000]);
    }

    public function test_stock_movements_update_stock_and_history(): void
    {
        $this->actingAs($this->shop->user);

        $this->post('/pro/gestion/stock', ['product_id' => $this->product->id, 'type' => 'in', 'quantity' => 5, 'reason' => 'purchase', 'unit_cost' => 3200])->assertSessionHasNoErrors();
        $this->assertSame(15, $this->product->fresh()->stock);
        $this->assertSame(3200, $this->product->fresh()->cost_price, 'Le dernier prix d\'achat est retenu.');

        $this->post('/pro/gestion/stock', ['product_id' => $this->product->id, 'type' => 'out', 'quantity' => 2, 'reason' => 'loss']);
        $this->post('/pro/gestion/stock', ['product_id' => $this->product->id, 'type' => 'adjust', 'quantity' => 12, 'reason' => 'inventory']);
        $this->assertSame(12, $this->product->fresh()->stock);
        $this->assertSame([5, -2, -1], $this->shop->stockMovements()->orderBy('id')->pluck('quantity')->all());

        // Le stock ne devient jamais négatif.
        $this->post('/pro/gestion/stock', ['product_id' => $this->product->id, 'type' => 'out', 'quantity' => 50, 'reason' => 'sale'])->assertSessionHasErrors('quantity');
        $this->assertSame(12, $this->product->fresh()->stock);

        $this->get('/pro/gestion/stock')->assertOk()->assertSee($this->product->name);
        $this->get('/pro/gestion/stock/mouvements')->assertOk()->assertSee('Achat');
    }

    public function test_invoice_takes_stock_out_and_cancellation_restores_it(): void
    {
        $this->actingAs($this->shop->user)->post('/pro/gestion/factures', [
            'kind' => 'invoice', 'issued_at' => now()->toDateString(), 'customer_name' => 'Awa',
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 5000],
                ['description' => 'Pose', 'quantity' => 1, 'unit_price' => 2000],
            ],
            'discount' => 1000, 'amount_paid' => 10000,
        ])->assertRedirect();

        $invoice = Invoice::first();
        $this->assertSame('F-'.now()->format('Y').'-0001', $invoice->number);
        $this->assertSame(16000, $invoice->total);
        $this->assertSame('partial', $invoice->status);
        $this->assertSame(7, $this->product->fresh()->stock);

        $this->post("/pro/gestion/factures/{$invoice->id}/paiement", ['amount' => 6000]);
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->get("/pro/gestion/factures/{$invoice->id}/imprimer")->assertOk()->assertSee('F-'.now()->format('Y').'-0001')->assertDontSee('Marge');

        $this->post("/pro/gestion/factures/{$invoice->id}/annuler");
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_invoice_cannot_sell_more_than_stock(): void
    {
        $this->actingAs($this->shop->user)->post('/pro/gestion/factures', [
            'kind' => 'invoice', 'issued_at' => now()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'quantity' => 11, 'unit_price' => 5000]],
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, Invoice::count());
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_quote_leaves_stock_until_converted(): void
    {
        $this->actingAs($this->shop->user)->post('/pro/gestion/factures', [
            'kind' => 'quote', 'issued_at' => now()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 5000]],
        ]);
        $quote = Invoice::first();
        $this->assertSame('D-'.now()->format('Y').'-0001', $quote->number);
        $this->assertSame(10, $this->product->fresh()->stock);

        $this->post("/pro/gestion/factures/{$quote->id}/convertir")->assertRedirect();
        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertSame($quote->id, Invoice::where('kind', 'invoice')->first()->converted_from_id);
    }

    public function test_report_computes_margin_expenses_and_net_profit(): void
    {
        $this->actingAs($this->shop->user)->post('/pro/gestion/factures', [
            'kind' => 'invoice', 'issued_at' => now()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 5000]],
            'amount_paid' => 20000,
        ]);
        $this->post('/pro/gestion/depenses', ['spent_at' => now()->toDateString(), 'category' => 'Loyer', 'amount' => 3000])->assertSessionHasNoErrors();

        $totals = ShopReport::forPeriod($this->shop, 'month')->totals();
        $this->assertSame(20000, $totals['revenue']);
        $this->assertSame(12000, $totals['cogs']);
        $this->assertSame(8000, $totals['gross']);
        $this->assertSame(3000, $totals['expenses']);
        $this->assertSame(5000, $totals['net']);

        $this->get('/pro/gestion')->assertOk()->assertSee('Bénéfice net');
        $this->get('/pro/gestion/rapports')->assertOk()->assertSee('5 000');
        $csv = $this->get('/pro/gestion/rapports/export/ventes')->assertOk()->streamedContent();
        $this->assertStringContainsString($this->product->name, $csv);
    }

    public function test_documents_are_private_to_their_owner(): void
    {
        $this->actingAs($this->shop->user)->post('/pro/gestion/factures', [
            'kind' => 'invoice', 'issued_at' => now()->toDateString(),
            'lines' => [['description' => 'Main d\'œuvre', 'quantity' => 1, 'unit_price' => 5000]],
        ]);
        $invoice = Invoice::first();
        $other = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);

        $this->actingAs($other->user)->get("/pro/gestion/factures/{$invoice->id}")->assertNotFound();
        // Une autre boutique ne peut pas toucher au stock de celle-ci.
        $this->post('/pro/gestion/stock', ['product_id' => $this->product->id, 'type' => 'in', 'quantity' => 1, 'reason' => 'purchase'])->assertSessionHasErrors('product_id');
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_garage_gets_invoices_but_not_stock(): void
    {
        $garage = Provider::factory()->approved()->create(['type' => ProviderType::MECHANIC]);
        $this->actingAs($garage->user)->get('/pro/gestion')->assertOk()->assertDontSee('Valeur d\'achat');
        $this->get('/pro/gestion/factures/nouvelle')->assertOk();
        $this->get('/pro/gestion/stock')->assertForbidden();
    }
}
