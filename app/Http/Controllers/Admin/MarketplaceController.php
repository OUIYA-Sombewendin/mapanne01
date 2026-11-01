<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PartCategory;
use App\Models\PartRequest;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Notifications\SimpleMessage;
use App\Support\Listing;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.marketplace', [
            'products' => Listing::sort(Product::with(['provider:id,business_name,slug', 'category:id,name', 'photos', 'lock'])
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'ilike', Listing::like($q))
                    ->orWhere('reference', 'ilike', Listing::like($q))->orWhere('brand', 'ilike', Listing::like($q))))
                ->when($request->query('filter') === 'hidden', fn ($query) => $query->where('is_hidden', true))
                ->when($request->query('filter') === 'pending', fn ($query) => $query->whereNull('approved_at'))
                ->when($request->query('filter') === 'featured', fn ($query) => $query->where('is_featured', true))
                ->when($request->query('category'), fn ($query, $id) => $query->whereIn('part_category_id', PartCategory::familyIdsOf($id)))
                ->when($request->query('shop'), fn ($query, $id) => $query->where('provider_id', $id))
                ->when($request->query('condition'), fn ($query, $c) => $query->where('condition', $c))
                ->when($request->query('stock') === 'out', fn ($query) => $query->where('stock', 0)->where('on_order', false))
                ->when($request->query('stock') === 'in', fn ($query) => $query->where('stock', '>', 0))
                ->when($request->query('price_min'), fn ($query, $v) => $query->where('price', '>=', (int) $v))
                ->when($request->query('price_max'), fn ($query, $v) => $query->where('price', '<=', (int) $v)), $request, [
                    'name' => 'name',
                    'shop' => fn ($q, $dir) => $q->orderBy(Provider::select('business_name')->whereColumn('providers.id', 'products.provider_id'), $dir),
                    'price' => 'price',
                    'stock' => 'stock',
                    'views' => 'views_count',
                    'contacts' => 'contacts_count',
                    'created' => 'created_at',
                ], 'created')
                ->paginate(30)
                ->withQueryString(),
            'shops' => Provider::whereIn('type', ProviderType::sellingSlugs())->orderBy('business_name')->get(['id', 'business_name']),
            'categories' => PartCategory::roots()->withCount('products')->with(['children' => fn ($q) => $q->withCount('products')])->orderBy('position')->get(),
            'pendingCount' => Product::whereNull('approved_at')->count(),
            'requests' => PartRequest::with(['user:id,name', 'city:id,name'])->withCount('offers')->latest()->take(15)->get(),
        ]);
    }

    /** Pré-modération : publie un produit en attente. */
    public function approve(Product $product): RedirectResponse
    {
        $product->forceFill(['approved_at' => now()])->save();
        ActivityLog::record('product.approved', $product);

        return back()->with('success', 'Produit publié.');
    }

    public function toggleFeatured(Product $product): RedirectResponse
    {
        $product->forceFill(['is_featured' => ! $product->is_featured])->save();

        return back()->with('success', $product->is_featured ? 'Produit mis à la une.' : 'Produit retiré de la une.');
    }

    /** Masquer un produit (contrefaçon, prix trompeur, photo inappropriée…) avec un motif visible du vendeur. */
    public function hide(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $product->forceFill(['is_hidden' => true, 'hidden_reason' => $data['reason']])->save();
        ActivityLog::record('product.hidden', $product, ['reason' => $data['reason']]);

        Notifier::send($product->provider->user, new SimpleMessage(
            'Un de vos produits a été masqué',
            ["« {$product->name} » n'est plus visible sur le marketplace.", 'Motif : '.$data['reason'], 'Corrigez l\'annonce puis contactez le support pour la republier.'],
            'Voir mon catalogue', route('pro.products.index'),
        ), 'notify_seller_product_hidden');

        return back()->with('success', 'Produit masqué.');
    }

    /** Fermer une demande de pièce abusive ou hors sujet. */
    public function closeRequest(PartRequest $partRequest): RedirectResponse
    {
        $partRequest->forceFill(['status' => 'closed'])->save();
        ActivityLog::record('part_request.closed_by_admin', $partRequest);

        return back()->with('success', 'Demande clôturée.');
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->forceFill(['is_hidden' => false, 'hidden_reason' => null])->save();
        ActivityLog::record('product.restored', $product);

        return back()->with('success', 'Produit republié.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('part_categories', 'name')],
            'icon' => ['nullable', 'string', 'max:16'],
            // Deux niveaux seulement : une sous-catégorie a pour parent une catégorie principale.
            'parent_id' => ['nullable', Rule::exists('part_categories', 'id')->whereNull('parent_id')],
        ]);
        $slug = Str::slug($data['name']);

        PartCategory::create($data + [
            'slug' => PartCategory::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug,
            'position' => (int) PartCategory::where('parent_id', $data['parent_id'] ?? null)->max('position') + 1,
        ]);

        return back()->with('success', empty($data['parent_id']) ? 'Catégorie de pièces ajoutée.' : 'Sous-catégorie ajoutée.');
    }
}
