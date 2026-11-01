<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Support\ProviderStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ce que MaPanne apporte à la fiche : vues, appels, WhatsApp, dépannages, avis, ce mois-ci et le précédent. */
class StatsController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $request->user()->provider;
        $days = (int) $request->query('periode', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $to = now()->addDay()->startOfDay();
        $from = $to->copy()->subDays($days);

        return view('pro.stats', [
            'provider' => $provider,
            'days' => $days,
            'current' => ProviderStats::totals($provider, $from, $to),
            'previous' => ProviderStats::totals($provider, $from->copy()->subDays($days), $from),
            'series' => ProviderStats::daily($provider, $days),
            'products' => $provider->canSellParts()
                ? $provider->products()->orderByDesc('views_count')->take(5)->get(['id', 'name', 'slug', 'views_count', 'contacts_count'])
                : collect(),
        ]);
    }
}
