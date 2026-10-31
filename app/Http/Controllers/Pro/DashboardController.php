<?php

namespace App\Http\Controllers\Pro;

use App\Enums\Availability;
use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $request->user()->provider;

        return view('pro.dashboard', [
            'provider' => $provider,
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
