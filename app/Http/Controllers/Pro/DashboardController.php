<?php

namespace App\Http\Controllers\Pro;

use App\Enums\Availability;
use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\ShopReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $provider = $user->provider;
        $managing = feature('shop_management_enabled');
        $sells = $provider->canSellParts();

        // Chiffres du mois et de la boutique, selon ce que le rôle du compte permet de voir.
        $kpis = [
            'month' => $managing && $user->canShop('reports') ? ShopReport::forPeriod($provider, 'month')->totals() : null,
            'salesToday' => $managing && $user->canShop('invoices')
                ? (int) $provider->invoices()->sales()->whereDate('issued_at', today())->sum('total') : null,
            'unpaid' => $managing && $user->canShop('invoices')
                ? (int) $provider->invoices()->where('kind', 'invoice')->whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - amount_paid')) : null,
            'products' => $sells ? $provider->products()->count() : null,
            'lowStock' => $sells ? $provider->products()->where('on_order', false)->whereRaw('stock <= COALESCE(stock_alert, 0)')->count() : null,
            'views' => $sells ? (int) $provider->products()->sum('views_count') : null,
            'contacts' => $sells ? (int) $provider->products()->sum('contacts_count') : null,
        ];

        return view('pro.dashboard', [
            'provider' => $provider,
            'kpis' => $kpis,
            'recentInvoices' => $managing && $user->canShop('invoices') ? $provider->invoices()->with('seller:id,name')->latest('id')->take(5)->get() : collect(),
            'previousLogin' => session('previous_login_at') ? Carbon::parse(session('previous_login_at')) : null,
            'completion' => $provider->profileCompletion(),
            'latestReviews' => $provider->reviews()->where('is_hidden', false)->with('user:id,name')->latest()->take(5)->get(),
            'favoritesCount' => DB::table('favorites')->where('provider_id', $provider->id)->count(),
            'isOpen' => $provider->load('schedules')->isOpenAt(now()),
        ]);
    }

    /** Bouton « Disponible / Occupé / Indisponible » du tableau de bord. */
    public function availability(Request $request): RedirectResponse
    {
        $data = $request->validate(['availability' => ['required', Rule::enum(Availability::class)]]);

        $provider = $request->user()->provider;
        $provider->availability = Availability::from($data['availability']);
        $provider->save();

        return back()->with('success', 'Statut : '.$provider->availability->label());
    }

    /** Après un refus, le prestataire corrige sa fiche puis la renvoie en vérification. */
    public function resubmit(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        abort_unless($provider->status === ProviderStatus::Rejected, 403);

        $provider->status = ProviderStatus::Pending;
        $provider->save();
        ActivityLog::record('provider.resubmitted', $provider);

        return back()->with('success', 'Votre fiche a été renvoyée à l\'équipe MaPanne pour vérification.');
    }
}
