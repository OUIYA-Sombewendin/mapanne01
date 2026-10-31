<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PartCategory;
use App\Models\PartRequest;
use App\Models\Product;
use App\Notifications\SimpleMessage;
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
            'products' => Product::with(['provider:id,business_name,slug', 'category:id,name', 'photos'])
                ->when($q !== '', fn ($query) => $query->where('name', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
                ->when($request->query('filter') === 'hidden', fn ($query) => $query->where('is_hidden', true))
                ->when($request->query('filter') === 'pending', fn ($query) => $query->whereNull('approved_at'))
                ->when($request->query('filter') === 'featured', fn ($query) => $query->where('is_featured', true))
                ->when($request->query('category'), fn ($query, $id) => $query->where('part_category_id', $id))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'categories' => PartCategory::withCount('products')->orderBy('position')->get(),
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
        ]);

        PartCategory::create($data + [
            'slug' => Str::slug($data['name']),
            'position' => (int) PartCategory::max('position') + 1,
        ]);

        return back()->with('success', 'Catégorie de pièces ajoutée.');
    }
}
