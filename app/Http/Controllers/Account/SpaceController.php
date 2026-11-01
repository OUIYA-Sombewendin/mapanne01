<?php

namespace App\Http\Controllers\Account;

use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\AssistRequest;
use App\Support\ClientSpace;
use App\Support\MaintenanceTasks;
use App\Support\SearchProblems;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace client : tableau de bord et pages « Mes … » (dépannages, véhicules, favoris, avis).
 * Le profil et le mot de passe restent dans AccountController.
 */
class SpaceController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user()->load(['vehicles.reminders', 'city']);

        return view('account.space', [
            'user' => $user,
            'activeAssist' => ClientSpace::activeAssist($user),
            'alerts' => ClientSpace::alerts($user),
            'favorites' => $user->favorites()->visible()->with('neighborhood:id,name')->latest('favorites.created_at')->take(4)->get(),
            'requests' => feature('marketplace_enabled') && feature('part_requests_enabled')
                ? $user->partRequests()->withCount('offers')->latest()->take(3)->get() : collect(),
            'problems' => collect(SearchProblems::ALL)->take(6),
        ]);
    }

    public function assists(Request $request): View
    {
        abort_unless(feature('assist_enabled'), 404);

        return view('account.assists', [
            'assists' => AssistRequest::where('user_id', $request->user()->id)->with('provider:id,business_name,slug')->latest()->paginate(15),
        ]);
    }

    public function vehicles(Request $request): View
    {
        return view('account.vehicles', [
            'vehicles' => $request->user()->vehicles()->with('reminders')->get(),
            'vehicleTypes' => VehicleType::options(),
            'energies' => MaintenanceTasks::energies(),
        ]);
    }

    public function favorites(Request $request): View
    {
        return view('account.favorites', [
            'favorites' => $request->user()->favorites()->visible()->with(['neighborhood:id,name', 'city:id,name'])->latest('favorites.created_at')->get(),
        ]);
    }

    public function reviews(Request $request): View
    {
        return view('account.reviews', [
            'reviews' => $request->user()->reviews()->with('provider:id,business_name,slug')->latest()->get(),
        ]);
    }
}
