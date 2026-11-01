<?php

namespace App\Http\Controllers;

use App\Models\ChargePoint;
use App\Models\City;
use App\Models\Provider;
use App\Support\AssistProblems;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page « Moto et voiture électriques » : spécialistes vérifiés, points de recharge
 * et d'échange de batterie, pannes fréquentes et conseils.
 */
class ElectricController extends Controller
{
    public function __invoke(Request $request): View
    {
        $city = $request->integer('city') ?: null;
        $now = now();

        $specialists = Provider::visible()->whereNotNull('ev_verified_at')
            ->when($city, fn ($q) => $q->where('city_id', $city))
            ->with(['neighborhood:id,name', 'city:id,name', 'schedules', 'services' => fn ($q) => $q->active()->select('services.id', 'services.name')])
            ->orderByDesc('rating_avg')->orderByDesc('reviews_count')
            ->get();

        $points = ChargePoint::active()->with('city:id,name')
            ->when($city, fn ($q) => $q->where('city_id', $city))
            ->orderBy('name')->get();

        $markers = $specialists->filter(fn ($p) => $p->latitude !== null)->map(fn (Provider $p) => [
            'lat' => $p->latitude,
            'lng' => $p->longitude,
            'name' => $p->business_name,
            'icon' => '⚡',
            'url' => route('providers.show', $p),
            'meta' => 'Spécialiste électrique · '.$p->type->shortLabel(),
            'open' => $p->isOpenAt($now),
        ])->concat($points->map(fn (ChargePoint $c) => [
            'lat' => $c->latitude,
            'lng' => $c->longitude,
            'name' => $c->name,
            'icon' => $c->icon(),
            'url' => $c->mapUrl(),
            'meta' => $c->kindLabel().($c->hours ? ' · '.$c->hours : ''),
            'open' => true,
        ]))->values();

        return view('pages.electric', [
            'specialists' => $specialists,
            'points' => $points,
            'markers' => $markers,
            'center' => ['lat' => (float) setting('map_default_lat'), 'lng' => (float) setting('map_default_lng'), 'label' => null],
            'cities' => City::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'city' => $city,
            'problems' => collect(AssistProblems::all())->reject(fn ($p) => ! empty($p['fuel']) || $p['kinds'] === ['tow'])->all(),
        ]);
    }
}
