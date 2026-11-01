<?php

namespace App\Http\Controllers;

use App\Models\ChargePoint;
use App\Models\City;
use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Support\AssistProblems;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $marketplace = feature('marketplace_enabled');

        // Chiffres de la page d'accueil, recalculés toutes les 10 minutes.
        $stats = Cache::remember('home.stats.v2', 600, fn () => [
            'providers' => Provider::visible()->count(),
            'night' => Provider::visible()->where('night_service', true)->count(),
            'cities' => Provider::visible()->distinct('city_id')->count('city_id'),
            'products' => Product::visible()->count(),
            'electric' => Provider::visible()->whereNotNull('ev_verified_at')->count(),
            'chargePoints' => ChargePoint::active()->count(),
            'byType' => Provider::visible()->selectRaw('type, COUNT(*) AS n')->groupBy('type')->pluck('n', 'type')->all(),
        ]);

        return view('home', [
            'types' => ProviderType::active(),
            'cities' => City::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stats' => $stats,
            // À la une d'abord, puis les mieux notés.
            'featured' => feature('home_show_featured')
                ? Provider::visible()
                    ->with(['services' => fn ($q) => $q->active()->select('services.id', 'services.name'), 'neighborhood:id,name', 'city:id,name', 'schedules'])
                    ->orderByDesc('is_featured')->orderByDesc('rating_avg')->orderByDesc('reviews_count')
                    ->take(4)->get()
                : collect(),
            'partCategories' => $marketplace ? PartCategory::roots()->orderBy('position')->get() : collect(),
            'latestProducts' => $marketplace
                ? Product::visible()->with(['photos', 'category:id,icon', 'provider.neighborhood:id,name'])->orderByDesc('is_featured')->latest()->take(8)->get()
                : collect(),
            'plans' => feature('subscriptions_enabled') && feature('home_show_plans')
                ? Plan::where('is_active', true)->whereNull('provider_types')->ordered()->get()
                : collect(),
            'problems' => (array) setting('home_problems'),
            'assistProblems' => AssistProblems::all(),
        ]);
    }
}
