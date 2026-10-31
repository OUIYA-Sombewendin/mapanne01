<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Report;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function show(Request $request, Provider $provider): View
    {
        $user = $request->user();
        $isOwner = $user && $provider->user_id === $user->id;

        // Un prestataire non validé n'existe pas pour le public : 404, pas 403,
        // pour ne pas révéler son existence. Seuls le titulaire et l'admin voient un aperçu.
        abort_unless($provider->isVisible() || $isOwner || $user?->isAdmin(), 404);

        // Un service désactivé par l'admin n'est plus affiché sur les fiches.
        $provider->load(['services' => fn ($q) => $q->active()->with('category'), 'schedules', 'photos', 'city', 'neighborhood']);

        return view('providers.show', [
            'provider' => $provider,
            'isPreview' => ! $provider->isVisible(),
            'reviews' => $provider->publishedReviews()->with('user:id,name')->latest()->paginate(10),
            'myReview' => $user ? $provider->reviews()->where('user_id', $user->id)->first() : null,
            'canReview' => feature('reviews_enabled') && $user?->isClient() && $provider->isVisible(),
            'isFavorite' => $user ? $user->favorites()->whereKey($provider->id)->exists() : false,
            'isOpen' => $provider->isOpenAt(now()),
            'days' => Schedule::DAYS,
            'reportReasons' => Report::reasons(),
            'products' => $provider->canSellParts()
                ? $provider->products()->visible()->with(['photos', 'category:id,icon'])->latest()->take(8)->get()
                : collect(),
            'productsCount' => $provider->canSellParts() ? $provider->products()->visible()->count() : 0,
        ]);
    }
}
