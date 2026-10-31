<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Toutes les variations de stock passent par ici : chaque changement laisse une trace
 * (mouvement de stock) et le stock ne peut jamais devenir négatif.
 */
class Inventory
{
    /**
     * Enregistre un mouvement. $quantity est positive ; le sens dépend de $type :
     * in = entrée, out = sortie, adjust = le stock devient $quantity (inventaire).
     */
    public static function move(Product $product, string $type, int $quantity, string $reason, ?int $unitCost = null, ?string $note = null, ?Invoice $invoice = null): StockMovement
    {
        return DB::transaction(function () use ($product, $type, $quantity, $reason, $unitCost, $note, $invoice) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->first();

            $delta = match ($type) {
                'in' => $quantity,
                'out' => -$quantity,
                'adjust' => $quantity - $locked->stock,
            };
            $after = $locked->stock + $delta;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Stock insuffisant pour « {$locked->name} » : {$locked->stock} disponible(s).",
                ]);
            }

            $changes = ['stock' => $after];
            // Un achat avec prix connu met à jour le prix d'achat (dernier prix payé).
            if ($type === 'in' && $reason === 'purchase' && $unitCost) {
                $changes['cost_price'] = $unitCost;
            }
            $locked->forceFill($changes)->saveQuietly();
            $product->setRawAttributes($locked->getAttributes(), true);

            return $locked->provider->stockMovements()->create([
                'product_id' => $locked->id,
                'type' => $type,
                'quantity' => $delta,
                'stock_after' => $after,
                'unit_cost' => $unitCost ?? $locked->cost_price,
                'reason' => $reason,
                'note' => $note,
                'invoice_id' => $invoice?->id,
                'user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Crée une facture ou un devis. Lignes : [product_id?, description, quantity, unit_price].
     * Une facture fait sortir du stock les pièces vendues ; un devis ne touche pas au stock.
     *
     * @param  array<int, array{product_id?:int|null, description?:string, quantity:int, unit_price:int}>  $lines
     */
    public static function createInvoice(Provider $provider, string $kind, array $data, array $lines, ?Invoice $from = null): Invoice
    {
        return DB::transaction(function () use ($provider, $kind, $data, $lines, $from) {
            // Verrou sur le prestataire : numéros de facture consécutifs même en cas de double clic.
            Provider::whereKey($provider->id)->lockForUpdate()->first();

            $products = $provider->products()->whereIn('id', collect($lines)->pluck('product_id')->filter())->get()->keyBy('id');

            $items = [];
            $subtotal = 0;
            foreach ($lines as $line) {
                $product = isset($line['product_id']) ? $products->get($line['product_id']) : null;
                $quantity = (int) $line['quantity'];
                $unitPrice = (int) $line['unit_price'];
                $items[] = [
                    'product_id' => $product?->id,
                    'description' => trim((string) ($line['description'] ?? '')) ?: ($product?->name ?? 'Article'),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => (int) ($product?->cost_price ?? 0),
                    'total' => $quantity * $unitPrice,
                ];
                $subtotal += $quantity * $unitPrice;
            }

            $discount = min($subtotal, (int) ($data['discount'] ?? 0));
            $total = $subtotal - $discount;
            $paid = $kind === 'invoice' ? min($total, (int) ($data['amount_paid'] ?? 0)) : 0;

            $invoice = $provider->invoices()->make([
                'kind' => $kind,
                'number' => self::nextNumber($provider, $kind),
                'status' => $kind === 'quote' ? 'draft' : Invoice::paymentStatus($total, $paid),
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'issued_at' => $data['issued_at'] ?? now()->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'amount_paid' => $paid,
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $invoice->converted_from_id = $from?->id;
            $invoice->user_id = auth()->id();
            $invoice->save();
            $invoice->items()->createMany($items);

            if ($kind === 'invoice') {
                foreach ($invoice->items as $item) {
                    if ($item->product_id) {
                        self::move($products[$item->product_id], 'out', $item->quantity, 'invoice', null, $invoice->number, $invoice);
                    }
                }
            }

            return $invoice;
        });
    }

    /** Annule une facture et remet en stock les pièces vendues. */
    public static function cancelInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            if ($invoice->kind === 'invoice' && ! $invoice->isCancelled()) {
                foreach ($invoice->items()->with('product')->get() as $item) {
                    if ($item->product) {
                        self::move($item->product, 'in', $item->quantity, 'cancel', $item->unit_cost ?: null, $invoice->number, $invoice);
                    }
                }
            }
            $invoice->update(['status' => 'cancelled']);
        });
    }

    public static function nextNumber(Provider $provider, string $kind): string
    {
        $prefix = trim((string) setting($kind === 'quote' ? 'quote_prefix' : 'invoice_prefix')) ?: ($kind === 'quote' ? 'D' : 'F');
        $base = $prefix.'-'.now()->format('Y').'-';
        $last = $provider->invoices()->where('number', 'like', $base.'%')->orderByDesc('number')->value('number');
        $next = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
