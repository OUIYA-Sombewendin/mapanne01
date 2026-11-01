<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SimpleMessage;
use App\Rules\BurkinaPhone;
use App\Support\ImageOptimizer;
use App\Support\Notifier;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Choix d'une offre par le prestataire. Pas de paiement en ligne : il paie par Orange Money ou Moov Money,
 * envoie la capture, le numéro utilisé et la référence ; l'équipe vérifie puis active (ou refuse).
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
            'rejected' => $provider->subscriptions()->where('status', 'rejected')->where('reviewed_at', '>=', now()->subDays(15))->with('plan')->latest('reviewed_at')->first(),
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

        return redirect()->route('pro.subscription.pay', $subscription);
    }

    /** Étape de paiement : numéros de réception, puis envoi de la preuve. */
    public function pay(Request $request, Subscription $subscription): View|RedirectResponse
    {
        $this->authorizePending($request, $subscription);

        return view('pro.subscription-pay', [
            'subscription' => $subscription->load('plan'),
            'operators' => Subscription::operators(),
        ]);
    }

    public function proof(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->authorizePending($request, $subscription);

        $request->merge(['payer_phone' => Phone::normalize((string) $request->input('payer_phone')) ?? $request->input('payer_phone')]);
        $data = $request->validate([
            'payment_operator' => ['required', Rule::in(array_keys(Subscription::operators()))],
            'payer_phone' => ['required', new BurkinaPhone],
            'payment_reference' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9.\-\/ ]+$/'],
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'payment_reference.regex' => 'La référence ne contient que des lettres, des chiffres et des points.',
            'proof.required' => 'Ajoutez la capture d\'écran du message de confirmation.',
        ]);
        $data['payment_reference'] = mb_strtoupper(trim($data['payment_reference']));

        if (Subscription::referenceUsed($data['payment_operator'], $data['payment_reference'], $subscription->id)) {
            return back()->withInput()->withErrors(['payment_reference' => 'Cette référence de transaction a déjà été utilisée. Vérifiez-la ou contactez le support.']);
        }

        if ($subscription->proof_path) {
            Storage::disk('local')->delete($subscription->proof_path);
        }
        unset($data['proof']);
        $subscription->forceFill($data + [
            'payment_method' => Subscription::OPERATORS[$data['payment_operator']],
            // Capture ré-encodée (sans métadonnées) et gardée sur le disque privé.
            'proof_path' => ImageOptimizer::store($request->file('proof'), 'payments', 1600, 'local'),
            'proof_submitted_at' => now(),
        ])->save();

        ActivityLog::record('subscription.proof_sent', $subscription, ['offre' => $subscription->plan->name, 'référence' => $data['payment_reference']]);
        if (feature('notify_admin_subscription_request') && filled(setting('admin_notification_email'))) {
            $provider = $subscription->provider;
            Notifier::sendTo(setting('admin_notification_email'), new SimpleMessage(
                'Paiement à vérifier : '.$provider->business_name,
                [
                    "{$provider->business_name} a payé l'offre {$subscription->plan->name} ({$subscription->plan->priceLabel()}).",
                    $subscription->operatorLabel().' depuis le '.Phone::format($data['payer_phone']).', référence '.$data['payment_reference'].'.',
                ],
                'Vérifier le paiement',
                route('admin.subscriptions.index', ['status' => 'proof']),
            ));
        }

        return redirect()->route('pro.subscription')->with('success', 'Preuve de paiement envoyée. L\'équipe la vérifie et active votre offre.');
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->provider_id === $request->user()->provider->id && $subscription->status === 'pending', 404);
        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', 'Demande annulée.');
    }

    private function authorizePending(Request $request, Subscription $subscription): void
    {
        abort_unless($subscription->provider_id === $request->user()->provider->id && $subscription->status === 'pending', 404);
    }
}
