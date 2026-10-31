<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SimpleMessage;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Choix d'une offre par le prestataire. Sans paiement en ligne, la demande reste
 * « en attente » jusqu'à ce que l'équipe confirme le paiement et l'active.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request): View
    {
        $provider = $request->user()->provider;

        return view('pro.subscription', [
            'provider' => $provider,
            'current' => $provider->currentSubscription(),
            'currentPlan' => $provider->currentPlan(),
            'plans' => Plan::availableFor($provider),
            'pending' => $provider->subscriptions()->where('status', 'pending')->with('plan')->first(),
            'history' => $provider->subscriptions()->with('plan')->take(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $plan = Plan::findOrFail($request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
        ])['plan_id']);
        abort_unless($plan->appliesTo($provider), 422);

        // Une seule demande en attente à la fois.
        $provider->subscriptions()->where('status', 'pending')->update(['status' => 'cancelled']);

        $subscription = $provider->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'pending',
            'amount' => $plan->price,
        ]);

        // Offre gratuite : activée immédiatement.
        if ($plan->price === 0) {
            $subscription->activate();
            ActivityLog::record('subscription.activated', $subscription, ['offre' => $plan->name]);

            return back()->with('success', "Offre {$plan->name} activée.");
        }

        ActivityLog::record('subscription.requested', $subscription, ['offre' => $plan->name]);
        if (feature('notify_admin_subscription_request') && filled(setting('admin_notification_email'))) {
            Notifier::sendTo(setting('admin_notification_email'), new SimpleMessage(
                'Demande d\'abonnement : '.$provider->business_name,
                ["{$provider->business_name} souhaite l'offre {$plan->name} ({$plan->priceLabel()}).", 'Activez-la après réception du paiement.'],
                'Voir les abonnements',
                route('admin.subscriptions.index', ['status' => 'pending']),
            ));
        }

        return back()->with('success', "Demande envoyée pour l'offre {$plan->name}. Elle sera activée dès réception de votre paiement.");
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->provider_id === $request->user()->provider->id && $subscription->status === 'pending', 404);
        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', 'Demande annulée.');
    }
}
