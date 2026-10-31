<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Subscription;
use App\Notifications\SimpleMessage;
use App\Support\Listing;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Suivi des abonnements : demandes à activer après paiement, abonnements en cours, échéances. */
class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        $q = trim((string) $request->query('q'));

        $query = Subscription::query()->with(['provider:id,business_name,slug,type,phone', 'plan:id,name,price', 'activator:id,name'])
            ->when($q !== '', fn ($query) => $query->whereHas('provider', fn ($p) => $p->where('business_name', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%')));

        match ($status) {
            'active' => $query->current(),
            'expiring' => $query->current()->whereNotNull('ends_at')->where('ends_at', '<=', now()->addDays(max(1, (int) setting('subscription_reminder_days')))),
            'all' => null,
            default => $query->where('status', $status),
        };

        return view('admin.subscriptions.index', [
            'subscriptions' => Listing::sort($query
                ->when($request->query('plan'), fn ($q, $id) => $q->where('plan_id', $id))
                ->when($request->query('payment'), fn ($q, $m) => $q->where('payment_method', $m)), $request, [
                    'date' => 'created_at',
                    'start' => 'starts_at',
                    'end' => 'ends_at',
                    'amount' => 'amount',
                ], 'date')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => [
                'pending' => Subscription::where('status', 'pending')->count(),
                'active' => Subscription::current()->count(),
                'expiring' => Subscription::current()->whereNotNull('ends_at')->where('ends_at', '<=', now()->addDays(max(1, (int) setting('subscription_reminder_days'))))->count(),
            ],
            'revenue' => (int) Subscription::whereIn('status', ['active', 'expired'])->where('starts_at', '>=', now()->startOfMonth())->sum('amount'),
            'plans' => Plan::where('is_active', true)->ordered()->get(),
            'providers' => Provider::orderBy('business_name')->get(['id', 'business_name']),
            'paymentMethods' => (array) setting('payment_methods'),
        ]);
    }

    /** Attribution directe d'une offre (paiement reçu au guichet, geste commercial…). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'plan_id' => ['required', 'exists:plans,id'],
        ] + $this->paymentRules());

        $subscription = Provider::findOrFail($data['provider_id'])->subscriptions()->create([
            'plan_id' => $data['plan_id'],
            'status' => 'pending',
            'amount' => $data['amount'] ?? Plan::find($data['plan_id'])->price,
        ]);

        return $this->doActivate($request, $subscription, $data);
    }

    public function activate(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->status === 'pending', 422);

        return $this->doActivate($request, $subscription, $request->validate($this->paymentRules()));
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        abort_unless(in_array($subscription->status, ['pending', 'active'], true), 422);
        $subscription->update(['status' => 'cancelled', 'ends_at' => $subscription->status === 'active' ? now() : $subscription->ends_at]);
        $subscription->provider->syncPlanBenefits();
        ActivityLog::record('subscription.cancelled', $subscription);

        return back()->with('success', 'Abonnement annulé.');
    }

    private function paymentRules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:0', 'max:3660'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function doActivate(Request $request, Subscription $subscription, array $data): RedirectResponse
    {
        $subscription->fill(array_filter([
            'amount' => $data['amount'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));
        $subscription->activate(
            $request->user(),
            ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null,
            isset($data['days']) ? (int) $data['days'] : null,
        );
        ActivityLog::record('subscription.activated', $subscription, ['offre' => $subscription->plan->name, 'référence' => $subscription->payment_reference]);

        $provider = $subscription->provider;
        Notifier::send($provider->user, new SimpleMessage(
            'Votre offre '.$subscription->plan->name.' est active',
            ['Votre abonnement '.$subscription->plan->name.' est actif'.($subscription->ends_at ? ' jusqu\'au '.$subscription->ends_at->format('d/m/Y') : '').'.'],
            'Ouvrir mon espace',
            route('pro.dashboard'),
        ));

        return back()->with('success', "Offre {$subscription->plan->name} activée pour {$provider->business_name}.");
    }
}
