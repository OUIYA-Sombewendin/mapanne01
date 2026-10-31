<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Provider;
use App\Models\StockMovement;
use App\Support\Inventory;
use App\Support\Listing;
use App\Support\ShopReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Inventaire et mouvements de stock (entrées, sorties, corrections). */
class StockController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $this->shop($request);
        $q = trim((string) $request->query('q'));

        $products = Listing::sort($shop->products()
            ->with('category:id,name,icon')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'ilike', Listing::like($q))
                ->orWhere('reference', 'ilike', Listing::like($q))
                ->orWhere('shelf', 'ilike', Listing::like($q))))
            ->when($request->query('filter') === 'low', fn ($query) => $query->where('on_order', false)->whereRaw('stock <= COALESCE(stock_alert, 0)'))
            ->when($request->query('filter') === 'out', fn ($query) => $query->where('stock', 0))
            ->when($request->query('filter') === 'nocost', fn ($query) => $query->whereNull('cost_price'))
            ->when($request->query('filter') === 'unpublished', fn ($query) => $query->where('is_active', false))
            ->when($request->query('category'), fn ($query, $id) => $query->where('part_category_id', $id))
            ->when($request->query('shelf'), fn ($query, $s) => $query->where('shelf', $s)), $request, [
                'name' => 'name',
                'shelf' => 'shelf',
                'stock' => 'stock',
                'cost' => 'cost_price',
                'price' => 'price',
                'margin' => fn ($q, $dir) => $q->orderByRaw("(price - cost_price) {$dir} NULLS LAST"),
                'value' => fn ($q, $dir) => $q->orderByRaw("(stock * COALESCE(cost_price, 0)) {$dir}"),
            ], 'name', 'asc')
            ->paginate(40)
            ->withQueryString();

        return view('pro.manage.stock', [
            'provider' => $shop,
            'products' => $products,
            'categories' => PartCategory::orderBy('position')->get(['id', 'name']),
            'shelves' => $shop->products()->whereNotNull('shelf')->distinct()->orderBy('shelf')->pluck('shelf'),
            'allProducts' => $shop->products()->orderBy('name')->get(['id', 'name', 'stock', 'cost_price']),
            'value' => ShopReport::stockValue($shop),
            'lowCount' => $shop->products()->where('on_order', false)->whereRaw('stock <= COALESCE(stock_alert, 0)')->count(),
            'reasons' => StockMovement::REASONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('provider_id', $shop->id)],
            'type' => ['required', Rule::in(['in', 'out', 'adjust'])],
            'quantity' => ['required', 'integer', 'min:'.($request->input('type') === 'adjust' ? 0 : 1), 'max:100000'],
            'reason' => ['required', Rule::in(array_keys(StockMovement::REASONS[$request->input('type')] ?? []))],
            'unit_cost' => ['nullable', 'integer', 'min:0', 'max:50000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['reason.in' => 'Choisissez un motif.']);

        $product = $shop->products()->findOrFail($data['product_id']);
        $movement = Inventory::move($product, $data['type'], (int) $data['quantity'], $data['reason'], $data['unit_cost'] ?? null, $data['note'] ?? null);

        return back()->with('success', "« {$product->name} » : stock passé à {$movement->stock_after}.");
    }

    public function movements(Request $request): View
    {
        $shop = $this->shop($request);

        return view('pro.manage.movements', [
            'provider' => $shop,
            'movements' => Listing::sort($shop->stockMovements()
                ->with(['product:id,name', 'invoice:id,number', 'user:id,name'])
                ->when($request->query('product'), fn ($q, $id) => $q->where('product_id', $id))
                ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
                ->when($request->query('reason'), fn ($q, $r) => $q->where('reason', $r))
                ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d)), $request, [
                    'date' => 'created_at',
                    'quantity' => 'quantity',
                    'product' => fn ($q, $dir) => $q->orderBy(Product::select('name')->whereColumn('products.id', 'stock_movements.product_id'), $dir),
                ], 'date')
                ->paginate(50)
                ->withQueryString(),
            'reasons' => array_merge(...array_values(StockMovement::REASONS)) + StockMovement::SYSTEM_REASONS,
            'products' => $shop->products()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function shop(Request $request): Provider
    {
        $shop = $request->user()->provider;
        abort_unless($shop->canSellParts(), 403, 'La gestion du stock est réservée aux vendeurs de pièces.');

        return $shop;
    }
}
