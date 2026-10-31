<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Rules\BurkinaPhone;
use App\Support\Inventory;
use App\Support\Listing;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Factures et devis : vente au comptoir, prestation d'atelier, suivi des paiements. */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $request->user()->provider;
        $kind = $request->query('kind') === 'quote' ? 'quote' : 'invoice';
        $q = trim((string) $request->query('q'));

        return view('pro.manage.invoices', [
            'provider' => $provider,
            'kind' => $kind,
            'invoices' => Listing::sort($provider->invoices()
                ->with('seller:id,name')
                ->where('kind', $kind)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->when($request->query('payment'), fn ($query, $m) => $query->where('payment_method', $m))
                ->when($request->query('seller'), fn ($query, $id) => $query->where('user_id', $id))
                ->when($request->query('from'), fn ($query, $d) => $query->whereDate('issued_at', '>=', $d))
                ->when($request->query('to'), fn ($query, $d) => $query->whereDate('issued_at', '<=', $d))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->where('number', 'ilike', Listing::like($q))
                    ->orWhere('customer_name', 'ilike', Listing::like($q))
                    ->orWhere('customer_phone', 'like', '%'.preg_replace('/\D/', '', $q).'%'))), $request, [
                        'number' => 'number',
                        'date' => 'issued_at',
                        'customer' => 'customer_name',
                        'total' => 'total',
                        'balance' => fn ($q, $dir) => $q->orderByRaw("(CASE WHEN status = 'cancelled' THEN 0 ELSE total - amount_paid END) {$dir}"),
                        'status' => 'status',
                    ], 'date')
                ->paginate(30)
                ->withQueryString(),
            'paymentMethods' => (array) setting('payment_methods'),
            'sellers' => collect([$provider->user])->concat($provider->members)->filter()->unique('id')->values(),
            'unpaid' => (int) $provider->invoices()->where('kind', 'invoice')->whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - amount_paid')),
        ]);
    }

    public function create(Request $request): View
    {
        $provider = $request->user()->provider;
        $sellsParts = $provider->canSellParts();

        return view('pro.manage.invoice-form', [
            'provider' => $provider,
            'kind' => $request->query('kind') === 'quote' ? 'quote' : 'invoice',
            'products' => $sellsParts
                ? $provider->products()->orderBy('name')->get(['id', 'name', 'price', 'stock'])
                : collect(),
            'paymentMethods' => (array) setting('payment_methods'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $request->merge(['customer_phone' => $request->filled('customer_phone') ? (Phone::normalize($request->input('customer_phone')) ?? $request->input('customer_phone')) : null]);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['invoice', 'quote'])],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', new BurkinaPhone],
            'issued_at' => ['required', 'date', 'before_or_equal:today'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('provider_id', $provider->id)],
            'lines.*.description' => ['nullable', 'required_without:lines.*.product_id', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0', 'max:50000000'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'amount_paid' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'lines.required' => 'Ajoutez au moins une ligne.',
            'lines.*.description.required_without' => 'Décrivez chaque ligne ou choisissez une pièce.',
        ]);

        $invoice = Inventory::createInvoice($provider, $data['kind'], $data, $data['lines']);

        return redirect()->route('pro.manage.invoices.show', $invoice)->with('success', $invoice->title().' enregistré'.($invoice->isQuote() ? '.' : 'e.'));
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);

        return view('pro.manage.invoice-show', [
            'provider' => $request->user()->provider,
            'invoice' => $invoice->load(['items', 'convertedFrom:id,number']),
            'converted' => Invoice::where('converted_from_id', $invoice->id)->first(['id', 'number']),
            'paymentMethods' => (array) setting('payment_methods'),
        ]);
    }

    /** Version imprimable (ou enregistrable en PDF depuis le navigateur). */
    public function print(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);

        return view('pro.invoices.print', ['invoice' => $invoice->load('items'), 'provider' => $request->user()->provider->load('city')]);
    }

    public function payment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_if($invoice->isQuote() || $invoice->isCancelled(), 422);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.max(1, $invoice->balance())],
            'payment_method' => ['nullable', 'string', 'max:60'],
        ], ['amount.max' => 'Le reste à payer est de '.number_format($invoice->balance(), 0, ',', ' ').' FCFA.']);

        $paid = $invoice->amount_paid + (int) $data['amount'];
        $invoice->update([
            'amount_paid' => $paid,
            'status' => Invoice::paymentStatus($invoice->total, $paid),
            'payment_method' => ($data['payment_method'] ?? null) ?: $invoice->payment_method,
        ]);

        return back()->with('success', 'Paiement enregistré.');
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_if($invoice->isCancelled(), 422);

        Inventory::cancelInvoice($invoice);

        return back()->with('success', $invoice->title().' annulé'.($invoice->isQuote() ? '.' : 'e. Les pièces sont remises en stock.'));
    }

    /** Transforme un devis accepté en facture (les pièces sortent alors du stock). */
    public function convert(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isQuote() && $invoice->status === 'draft', 422);

        $lines = $invoice->items->map(fn ($item) => $item->only(['product_id', 'description', 'quantity', 'unit_price']))->all();
        $new = Inventory::createInvoice($request->user()->provider, 'invoice', [
            'customer_name' => $invoice->customer_name,
            'customer_phone' => $invoice->customer_phone,
            'discount' => $invoice->discount,
            'notes' => $invoice->notes,
        ], $lines, $invoice);
        $invoice->update(['status' => 'accepted']);

        return redirect()->route('pro.manage.invoices.show', $new)->with('success', 'Devis transformé en '.$new->title().'.');
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->provider_id === $request->user()->provider->id, 404);
    }
}
