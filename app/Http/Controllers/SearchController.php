<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ServiceCategory;
use App\Services\ProviderSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = new ProviderSearch($request->query());
        $providers = $search->paginate();
        $now = now();

        // Données minimales pour la carte (pas de requête supplémentaire côté client).
        $markers = $providers->getCollection()->map(fn (Provider $p) => [
            'lat' => $p->latitude,
            'lng' => $p->longitude,
            'name' => $p->business_name,
            'icon' => $p->type->icon(),
            'url' => route('providers.show', $p),
            'meta' => trim(($p->distanceLabel() ? $p->distanceLabel().' · ' : '').$p->type->shortLabel()),
            'open' => $p->isOpenAt($now),
        ])->filter(fn ($m) => $m['lat'] !== null)->values();

        return view('search.index', [
            'providers' => $providers,
            'filters' => $search->filters,
            'center' => $search->center ?? ['lat' => (float) setting('map_default_lat'), 'lng' => (float) setting('map_default_lng'), 'label' => null],
            'hasCenter' => $search->center !== null,
            'markers' => $markers,
            'now' => $now,
            'types' => ProviderType::active(),
            'categories' => ServiceCategory::with(['services' => fn ($q) => $q->active()])->orderBy('position')->get(),
            'cities' => City::where('is_active', true)->orderBy('name')->with('neighborhoods:id,city_id,name')->get(),
            'radii' => ProviderSearch::radii(),
        ]);
    }
}
