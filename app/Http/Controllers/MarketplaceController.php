<?php

namespace App\Http\Controllers;

use App\Enums\VehicleType;
use App\Models\City;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\Report;
use App\Services\ProductSearch;
use App\Support\PartCatalog;
use App\Support\PriceWatch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        $search = new ProductSearch($request->query());

        return view('marketplace.index', [
            'products' => $search->paginate(),
            'filters' => $search->filters,
            'center' => $search->center,
            'currentCategory' => $search->category(),
            'categories' => PartCategory::tree(),
            'cities' => City::where('is_active', true)->orderBy('name')->with('neighborhoods:id,city_id,name')->get(),
            'vehicleTypes' => VehicleType::options(),
            'conditions' => feature('allow_used_parts') ? Product::CONDITIONS : [],
            'sorts' => ProductSearch::SORTS,
            'makes' => collect(PartCatalog::MAKES)->merge(ProductFitment::distinct()->orderBy('make')->pluck('make'))->unique()->sort()->values(),
            // Raccourcis « pièces pour mon véhicule » à partir des véhicules enregistrés du client.
            'myVehicles' => $request->user()?->isClient() ? $request->user()->vehicles : collect(),
        ]);
    }

    public function show(Request $request, Product $product): View
    {
        $user = $request->user();
        $product->load(['photos', 'fitments', 'category.parent', 'provider' => fn ($q) => $q->with('neighborhood', 'city', 'schedules', 'user')]);
        $isOwner = $user && $product->provider->user_id === $user->id;

        // Même règle que les fiches prestataires : 404 si non visible, aperçu pour le vendeur et l'admin.
        abort_unless($product->isVisible() || $isOwner || $user?->isAdmin(), 404);

        // Une vue comptée par visiteur et par produit (statistique pour le vendeur).
        // Une vue par visiteur (session) et par adresse IP sur 24 h : un robot ne peut pas gonfler le compteur.
        if ($product->isVisible() && ! $isOwner && ! $request->session()->has('viewed:'.$product->id)
            && RateLimiter::attempt('view:'.$product->id.':'.$request->ip(), 1, fn () => true, 86400)) {
            $product->increment('views_count');
            $request->session()->put('viewed:'.$product->id, true);
        }

        // Autres offres pour une pièce proche : même catégorie, premiers mots du nom en commun.
        $keyword = collect(preg_split('/\s+/', $product->name))->first(fn ($w) => mb_strlen($w) > 3) ?? $product->name;
        $similar = Product::visible()
            ->with(['photos', 'provider:id,business_name,slug,neighborhood_id', 'provider.neighborhood:id,name'])
            ->where('part_category_id', $product->part_category_id)
            ->where('products.id', '!=', $product->id)
            ->where('products.name', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $keyword).'%')
            ->orderBy('price')
            ->take(6)
            ->get();

        return view('marketplace.show', [
            'product' => $product,
            'shop' => $product->provider,
            'isPreview' => ! $product->isVisible(),
            'similar' => $similar,
            'priceWatch' => PriceWatch::forProduct($product),
            'moreFromShop' => $product->provider->products()->visible()->with('photos')
                ->where('products.id', '!=', $product->id)->latest()->take(4)->get(),
            'reportReasons' => Report::reasons(),
        ]);
    }

    /**
     * Compte un contact (appel ou WhatsApp), envoyé en arrière-plan par le navigateur
     * (navigator.sendBeacon) : les liens tel:/wa.me restent directs et fonctionnent sans JS.
     * C'est la statistique qui montre au vendeur ce que MaPanne lui apporte.
     */
    public function contact(Request $request, Product $product): Response
    {
        abort_unless($product->isVisible(), 404);

        // Un seul comptage par visiteur et par produit (session).
        $key = 'contacted:'.$product->id;
        if (! $request->session()->has($key) && RateLimiter::attempt('contact:'.$product->id.':'.$request->ip(), 1, fn () => true, 86400)) {
            $product->increment('contacts_count');
            $request->session()->put($key, true);
        }

        return response()->noContent();
    }
}
