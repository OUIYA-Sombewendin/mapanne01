<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Notifications\ProviderDecision;
use App\Rules\BurkinaPhone;
use App\Support\Notifier;
use App\Support\Phone;
use App\Support\ProviderProfiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function index(Request $request): View
    {
        $filter = (string) $request->query('status', 'pending');
        $status = ProviderStatus::tryFrom($filter);
        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $providers = Provider::query()
            ->with(['user:id,name,phone,email,is_active', 'city:id,name'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($filter === 'review', fn ($query) => $query->where('needs_review', true))
            ->when($filter === 'featured', fn ($query) => $query->where('is_featured', true))
            ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('business_name', 'ilike', $like)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', $like))
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $q).'%')))
            ->orderBy($status === ProviderStatus::Pending ? 'created_at' : 'updated_at', $status === ProviderStatus::Pending ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('admin.providers.index', [
            'providers' => $providers,
            'filter' => $filter,
            'status' => $status,
            'statuses' => ProviderStatus::cases(),
            'types' => ProviderType::cached()->values(),
            'counts' => Provider::selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'reviewCount' => Provider::where('needs_review', true)->count(),
        ]);
    }

    public function show(Provider $provider): View
    {
        $provider->load(['user', 'services', 'schedules', 'photos', 'city', 'neighborhood', 'approver']);

        return view('admin.providers.show', [
            'provider' => $provider,
            'completion' => $provider->profileCompletion(),
            'reports' => $provider->reports()->with('reporter:id,name')->latest()->get(),
            'logs' => ActivityLog::where('subject_type', $provider->getMorphClass())->where('subject_id', $provider->id)->with('user:id,name')->latest('created_at')->get(),
            // Contrôles automatiques pour aider la décision
            'checks' => $this->checks($provider),
        ]);
    }

    public function edit(Provider $provider): View
    {
        return view('admin.providers.edit', [
            'provider' => $provider,
            'types' => ProviderType::options(activeOnly: false),
            'cities' => City::orderBy('name')->with('neighborhoods:id,city_id,name')->get(),
            'paymentMethods' => (array) setting('payment_methods'),
        ]);
    }

    /** Correction d'une fiche par l'administration (sans repasser par le prestataire). */
    public function update(Request $request, Provider $provider): RedirectResponse
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone'),
            'whatsapp' => $request->filled('whatsapp') ? (Phone::normalize($request->input('whatsapp')) ?? $request->input('whatsapp')) : null,
        ]);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::exists('provider_types', 'slug')],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', new BurkinaPhone],
            'whatsapp' => ['nullable', new BurkinaPhone],
            'address' => ['nullable', 'string', 'max:200'],
            'city_id' => ['required', 'exists:cities,id'],
            'neighborhood_id' => ['nullable', Rule::exists('neighborhoods', 'id')->where('city_id', $request->input('city_id'))],
            'latitude' => ['required', 'numeric', 'between:9,15.5'],
            'longitude' => ['required', 'numeric', 'between:-6,2.5'],
            'rccm' => ['nullable', 'string', 'max:40'],
            'ifu' => ['nullable', 'string', 'max:40'],
            'delivery_details' => ['nullable', 'string', 'max:255'],
            'payment_methods' => ['array'],
            'payment_methods.*' => [Rule::in((array) setting('payment_methods'))],
        ] + (($kind = ProviderType::bySlug((string) $request->input('type'))?->profileKind()) ? ProviderProfiles::rules($kind) : []));

        $provider->fill(collect($data)->except('details')->all() + ['payment_methods' => []]);
        foreach (['is_formal', 'night_service'] as $flag) {
            $provider->{$flag} = $request->boolean($flag);
        }
        $provider->delivery_available = feature('delivery_enabled') && $request->boolean('delivery_available');
        $provider->is_featured = $request->boolean('is_featured');
        $provider->save();
        // Fiche métier du type choisi (le formulaire affiche la section correspondant au type sélectionné).
        if ($kind && $request->has('details.'.$kind)) {
            $provider->saveDetails((array) $request->input('details.'.$kind));
        }

        ActivityLog::record('provider.edited_by_admin', $provider, ['champs' => array_keys($provider->getChanges())]);

        return redirect()->route('admin.providers.show', $provider)->with('success', 'Fiche mise à jour.');
    }

    public function approve(Request $request, Provider $provider): RedirectResponse
    {
        abort_if($provider->status === ProviderStatus::Approved, 422);

        $provider->forceFill([
            'status' => ProviderStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'rejection_reason' => null,
            'needs_review' => false,
            'pending_changes' => null,
        ])->save();
        ActivityLog::record('provider.approved', $provider);
        $this->notify($provider);

        return redirect()->route('admin.providers.index')->with('success', "« {$provider->business_name} » est maintenant visible publiquement.");
    }

    public function reject(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [
            'reason.required' => 'Expliquez le motif du refus au prestataire.',
        ]);

        $provider->forceFill(['status' => ProviderStatus::Rejected, 'rejection_reason' => $data['reason']])->save();
        ActivityLog::record('provider.rejected', $provider, ['reason' => $data['reason']]);
        $this->notify($provider);

        return redirect()->route('admin.providers.index')->with('success', 'Inscription refusée.');
    }

    public function suspend(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $provider->forceFill(['status' => ProviderStatus::Suspended, 'rejection_reason' => $data['reason']])->save();
        ActivityLog::record('provider.suspended', $provider, ['reason' => $data['reason']]);
        $this->notify($provider);

        return back()->with('success', 'Prestataire suspendu : il n\'apparaît plus dans les recherches.');
    }

    public function reactivate(Provider $provider): RedirectResponse
    {
        abort_unless($provider->status === ProviderStatus::Suspended, 422);

        $provider->forceFill(['status' => ProviderStatus::Approved, 'rejection_reason' => null])->save();
        ActivityLog::record('provider.reactivated', $provider);
        $this->notify($provider);

        return back()->with('success', 'Prestataire réactivé.');
    }

    /** Les changements sensibles signalés ont été vérifiés. */
    public function markReviewed(Provider $provider): RedirectResponse
    {
        $provider->forceFill(['needs_review' => false, 'pending_changes' => null])->save();
        ActivityLog::record('provider.changes_reviewed', $provider);

        return back()->with('success', 'Changements marqués comme vérifiés.');
    }

    public function toggleFeatured(Provider $provider): RedirectResponse
    {
        $provider->forceFill(['is_featured' => ! $provider->is_featured])->save();
        ActivityLog::record($provider->is_featured ? 'provider.featured' : 'provider.unfeatured', $provider);

        return back()->with('success', $provider->is_featured ? 'Fiche mise à la une.' : 'Fiche retirée de la une.');
    }

    private function notify(Provider $provider): void
    {
        Notifier::send($provider->user, new ProviderDecision($provider), 'notify_provider_decision');
    }

    /** @return list<array{ok: bool|null, label: string}> null = avertissement */
    private function checks(Provider $provider): array
    {
        $user = $provider->user;
        $duplicatePhone = Provider::where('id', '!=', $provider->id)
            ->where(fn ($q) => $q->where('phone', $provider->phone)->orWhere('whatsapp', $provider->phone))
            ->first();
        $refusedBefore = Provider::where('id', '!=', $provider->id)->where('phone', $provider->phone)
            ->whereIn('status', [ProviderStatus::Rejected, ProviderStatus::Suspended])->exists();

        return [
            ['ok' => $user->hasVerifiedEmail(), 'label' => $user->hasVerifiedEmail() ? 'Email confirmé' : 'Email non confirmé'],
            ['ok' => $provider->latitude !== null && $provider->city
                && abs($provider->latitude - $provider->city->latitude) < 0.3 && abs($provider->longitude - $provider->city->longitude) < 0.3,
                'label' => 'Position GPS cohérente avec la ville déclarée'],
            ['ok' => $provider->photos->isNotEmpty() || filled($provider->logo_path) ? true : null, 'label' => $provider->photos->isNotEmpty() ? $provider->photos->count().' photo(s) fournie(s)' : 'Aucune photo de l\'atelier'],
            ['ok' => $provider->schedules->isNotEmpty() ? true : null, 'label' => $provider->schedules->isNotEmpty() ? 'Horaires renseignés' : 'Horaires non renseignés'],
            ['ok' => ! $duplicatePhone, 'label' => $duplicatePhone ? 'Numéro déjà utilisé par « '.$duplicatePhone->business_name.' »' : 'Numéro unique sur la plateforme'],
            ['ok' => ! $refusedBefore, 'label' => $refusedBefore ? 'Numéro déjà vu sur une fiche refusée ou suspendue' : 'Aucun antécédent de refus'],
        ];
    }
}
