<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\ProfileField;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Service;
use App\Notifications\ProviderChangesReviewed;
use App\Notifications\ProviderDecision;
use App\Rules\BurkinaPhone;
use App\Support\Listing;
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
        $like = Listing::like($q);

        $query = Provider::query()
            ->with(['user:id,name,phone,email,is_active', 'city:id,name', 'lock'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($filter === 'review', fn ($query) => $query->where('needs_review', true))
            ->when($filter === 'featured', fn ($query) => $query->where('is_featured', true))
            ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->query('city'), fn ($query, $city) => $query->where('city_id', $city))
            ->when(Listing::flag($request, 'night') !== null, fn ($query) => $query->where('night_service', Listing::flag($request, 'night')))
            ->when(Listing::flag($request, 'formal') !== null, fn ($query) => $query->where('is_formal', Listing::flag($request, 'formal')))
            ->when(Listing::flag($request, 'photos') !== null, fn ($query) => Listing::flag($request, 'photos') ? $query->has('photos') : $query->doesntHave('photos'))
            ->when(Listing::flag($request, 'blocked') !== null, fn ($query) => $query->whereHas('user', fn ($u) => $u->where('is_active', ! Listing::flag($request, 'blocked'))))
            ->when($request->query('from'), fn ($query, $d) => $query->whereDate('providers.created_at', '>=', $d))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('business_name', 'ilike', $like)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', $like))
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $q).'%')));

        // Par défaut : les plus anciennes inscriptions en attente d'abord, sinon les dernières modifications.
        $pendingFirst = $status === ProviderStatus::Pending;
        $providers = Listing::sort($query, $request, [
            'name' => 'providers.business_name',
            'type' => 'providers.type',
            'city' => fn ($q, $dir) => $q->orderBy(City::select('name')->whereColumn('cities.id', 'providers.city_id'), $dir),
            'status' => 'providers.status',
            'rating' => 'providers.rating_avg',
            'created' => 'providers.created_at',
            'updated' => 'providers.updated_at',
        ], $pendingFirst ? 'created' : 'updated', $pendingFirst ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('admin.providers.index', [
            'providers' => $providers,
            'filter' => $filter,
            'status' => $status,
            'statuses' => ProviderStatus::cases(),
            'types' => ProviderType::cached()->values(),
            'cities' => City::orderBy('name')->get(['id', 'name']),
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
        $kind = $provider->profileKind();

        return view('admin.providers.edit', [
            'provider' => $provider->load('services'),
            'kind' => $kind,
            'copy' => ProviderProfiles::copy($kind),
            'categories' => ProviderProfiles::serviceCategories($kind),
            'customFields' => ProfileField::forProvider($provider),
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

        $newType = ProviderType::bySlug((string) $request->input('type'));
        $kind = $newType?->profileKind();
        // Services autorisés : ceux du métier (du nouveau métier si l'admin change le type).
        $allowedServices = Service::whereIn('service_category_id', ProviderProfiles::serviceCategories($kind)->pluck('id'))->pluck('id')->all();
        $customFields = ProfileField::forProvider($provider);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::exists('provider_types', 'slug')],
            'services' => ['array'],
            'services.*' => ['integer'],
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
        ] + ($kind ? ProviderProfiles::rules($kind) : []) + ProfileField::rulesFor($customFields));

        $typeChanged = $newType && $newType->slug !== $provider->getRawOriginal('type');
        $provider->fill(collect($data)->except(['details', 'services', 'custom'])->all() + ['payment_methods' => []]);
        $provider->custom_fields = array_merge((array) $provider->custom_fields, $customFields->mapWithKeys(fn ($f) => [$f->key => $f->cast($request->input('custom.'.$f->key))])->all());
        foreach (['is_formal', 'night_service'] as $flag) {
            $provider->{$flag} = $request->boolean($flag);
        }
        $provider->delivery_available = feature('delivery_enabled') && $request->boolean('delivery_available');
        $provider->is_featured = $request->boolean('is_featured');
        $provider->save();
        // Un changement de métier retire les services de l'ancien métier.
        $provider->services()->sync(array_values(array_intersect(
            $request->boolean('services_present') ? array_map('intval', (array) ($data['services'] ?? [])) : $provider->services()->pluck('services.id')->all(),
            $allowedServices,
        )));
        if ($typeChanged) {
            ActivityLog::record('provider.type_changed', $provider, ['nouveau métier' => $newType->label()]);
        }
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

    /** Les changements sensibles signalés ont été vérifiés (ou la demande de modification est refusée). */
    public function markReviewed(Request $request, Provider $provider): RedirectResponse
    {
        $requested = $provider->requestedChanges();
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;
        $provider->forceFill(['needs_review' => false, 'pending_changes' => null])->save();

        if ($requested) {
            ActivityLog::record('provider.changes_refused', $provider, ['champs' => array_keys($requested), 'motif' => $reason]);
            Notifier::send($provider->user, new ProviderChangesReviewed($provider, false, $this->labels($requested), $reason));

            return back()->with('success', 'Demande de modification refusée : le prestataire est prévenu.');
        }
        ActivityLog::record('provider.changes_reviewed', $provider);

        return back()->with('success', 'Changements marqués comme vérifiés.');
    }

    /** Applique les modifications demandées par le prestataire sur sa fiche validée. */
    public function applyChanges(Provider $provider): RedirectResponse
    {
        $applied = $provider->applyRequestedChanges();
        abort_if($applied === [], 422);
        ActivityLog::record('provider.changes_applied', $provider, $applied);
        Notifier::send($provider->user, new ProviderChangesReviewed($provider, true, $this->labels($applied)));

        return back()->with('success', 'Modifications appliquées sur la fiche : le prestataire est prévenu.');
    }

    /** @return list<string> */
    private function labels(array $changes): array
    {
        return collect(array_keys($changes))->map(fn ($f) => Provider::LOCKED_LABELS[$f] ?? $f)->unique()->values()->all();
    }

    public function toggleFeatured(Provider $provider): RedirectResponse
    {
        $provider->forceFill(['is_featured' => ! $provider->is_featured])->save();
        ActivityLog::record($provider->is_featured ? 'provider.featured' : 'provider.unfeatured', $provider);

        return back()->with('success', $provider->is_featured ? 'Fiche mise à la une.' : 'Fiche retirée de la une.');
    }

    private function notify(Provider $provider): void
    {
        $notification = new ProviderDecision($provider);
        if (feature('notify_provider_decision')) {
            Notifier::send($provider->user, $notification);

            return;
        }
        // Emails de décision désactivés : la décision reste visible dans la cloche de l'espace pro.
        try {
            $provider->user->notifyNow($notification, ['database']);
        } catch (\Throwable $e) {
            report($e);
        }
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
