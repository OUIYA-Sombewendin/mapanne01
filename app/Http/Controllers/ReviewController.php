<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Notifications\SimpleMessage;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ReviewController extends Controller
{
    /**
     * Règles anti-manipulation :
     * - seuls les comptes clients actifs notent (pas les prestataires entre eux) ;
     * - un seul avis par client et par prestataire (une nouvelle soumission le remplace) ;
     * - prestataire visible uniquement ;
     * - nombre d'avis par heure et par compte limité (réglage admin) ;
     * - pré-modération possible (réglage admin) : l'avis attend une validation avant publication.
     */
    public function store(Request $request, Provider $provider): RedirectResponse
    {
        $user = $request->user();
        abort_unless(feature('reviews_enabled') && $user->isClient() && $provider->isVisible(), 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $key = 'review:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, max(1, (int) setting('reviews_per_hour')))) {
            return back()->withErrors(['rating' => 'Vous avez publié beaucoup d\'avis récemment. Réessayez plus tard.']);
        }
        RateLimiter::hit($key, 3600);

        $review = $provider->reviews()->where('user_id', $user->id)->first()
            ?? $provider->reviews()->make()->forceFill(['user_id' => $user->id]);
        $isNew = ! $review->exists;
        $review->fill($data);
        $review->ip_address = $request->ip();
        $review->is_pending = feature('reviews_premoderation');
        $review->save();

        if ($isNew && ! $review->is_pending) {
            Notifier::send($provider->user, new SimpleMessage(
                'Nouvel avis sur '.$provider->business_name,
                ["Un client vous a donné {$review->rating}/5.", $review->comment ? '« '.$review->comment.' »' : 'Sans commentaire.'],
                'Voir mes avis', route('providers.show', $provider).'#avis',
            ), 'notify_provider_new_review');
        }

        return back()->with('success', $review->is_pending
            ? 'Merci ! Votre avis sera publié après vérification.'
            : 'Merci ! Votre avis a été publié.');
    }

    public function destroy(Request $request, Provider $provider): RedirectResponse
    {
        $provider->reviews()->where('user_id', $request->user()->id)->first()?->delete();

        return back()->with('success', 'Votre avis a été supprimé.');
    }
}
