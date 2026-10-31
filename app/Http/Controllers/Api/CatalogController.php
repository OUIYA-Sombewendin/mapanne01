<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProviderResource;
use App\Models\City;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ServiceCategory;
use App\Services\ProductSearch;
use App\Services\ProviderSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    /** GET /api/v1/providers?lat=&lng=&radius=&type=&service=&q=&open_now=1&night=1 */
    public function providers(Request $request): AnonymousResourceCollection
    {
        return ProviderResource::collection((new ProviderSearch($request->query()))->paginate());
    }

    public function provider(Provider $provider): ProviderResource
    {
        abort_unless($provider->isVisible(), 404);

        return new ProviderResource($provider->load(['services', 'schedules', 'city', 'neighborhood']));
    }

    /** GET /api/v1/products?q=&category=&vehicle=&condition=&in_stock=1&price_min=&price_max=&lat=&lng=&city=&sort= */
    public function products(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection((new ProductSearch($request->query()))->paginate());
    }

    public function product(Product $product): ProductResource
    {
        abort_unless($product->isVisible(), 404);

        return new ProductResource($product->load(['photos', 'category', 'provider.neighborhood', 'provider.city']));
    }

    public function references(): JsonResponse
    {
        return response()->json([
            'part_categories' => PartCategory::orderBy('position')->get(['name', 'slug', 'icon']),
            'provider_types' => collect(ProviderType::active())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'categories' => ServiceCategory::with(['services' => fn ($q) => $q->active()->select('id', 'service_category_id', 'name', 'slug')])
                ->orderBy('position')->get(['id', 'name', 'slug', 'icon']),
            'cities' => City::where('is_active', true)->with('neighborhoods:id,city_id,name,latitude,longitude')
                ->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']),
            'search_radii' => ProviderSearch::radii(),
        ]);
    }
}
