<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Support\ShopReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestion de l'activité du prestataire : vue d'ensemble du mois.
 * Stock, factures, dépenses et rapports sont dans leurs propres contrôleurs.
 */
class ManagementController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $request->user()->provider;
        $report = ShopReport::forPeriod($provider, 'month');
        $canStock = $provider->canSellParts() && $provider->hasPlanFeature('stock');

        return view('pro.manage.index', [
            'provider' => $provider,
            'totals' => $report->totals(),
            'series' => $report->revenueSeries(),
            'stockValue' => $canStock ? ShopReport::stockValue($provider) : null,
            'lowStock' => $canStock
                ? $provider->products()->where('on_order', false)->whereRaw('stock <= COALESCE(stock_alert, 0)')->orderBy('stock')->take(8)->get()
                : collect(),
            'recentInvoices' => $provider->hasPlanFeature('invoices') ? $provider->invoices()->latest('id')->take(6)->get() : collect(),
            'unpaid' => (int) $provider->invoices()->where('kind', 'invoice')->whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - amount_paid')),
        ]);
    }
}
