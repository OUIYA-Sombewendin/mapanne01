<x-layouts.admin :title="$provider->business_name">
    <a href="{{ route('admin.providers.index', ['status' => $provider->status->value]) }}" class="text-sm text-ink-500">← Prestataires</a>
    <div class="mt-2 mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-extrabold">{{ $provider->type->icon() }} {{ $provider->business_name }}</h1>
        <span class="chip {{ $provider->status->color() }}">{{ $provider->status->label() }}</span>
        @if ($provider->is_featured) <span class="chip bg-brand-100 text-brand-800">⭐ À la une</span> @endif
        <a href="{{ route('providers.show', $provider) }}" target="_blank" class="text-sm text-brand-600">Aperçu de la fiche ↗</a>
        <span class="ml-auto"></span>
        <x-lock-button :model="$provider" />
        @if ($provider->isLocked())
            <span class="text-xs text-amber-800">Fiche verrouillée : validation, suspension et modification désactivées.</span>
        @else
            <a href="{{ route('admin.providers.edit', $provider) }}" class="btn btn-ghost btn-sm">✏️ Modifier la fiche</a>
        @endif
    </div>

    @if ($provider->needs_review)
        <section class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-amber-900">Changements sensibles faits par le prestataire</p>
                    <ul class="mt-2 space-y-1 text-sm text-amber-900">
                        @foreach ((array) $provider->pending_changes as $field => $change)
                            <li><strong>{{ $field }}</strong> : « {{ $change['avant'] ?? '—' }} » → « {{ $change['après'] ?? '—' }} »</li>
                        @endforeach
                    </ul>
                </div>
                <form method="POST" action="{{ route('admin.providers.reviewed', $provider) }}">@csrf
                    <button class="btn btn-dark btn-sm">✓ Changements vérifiés</button>
                </form>
            </div>
        </section>
    @endif

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            <section class="card p-5">
                <h2 class="mb-3 font-bold">Informations</h2>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Type' => $provider->type->label(),
                        'Responsable' => $provider->manager_name ?: $provider->user->name,
                        'Téléphone' => \App\Support\Phone::format($provider->phone),
                        'WhatsApp' => \App\Support\Phone::format($provider->whatsapp),
                        'Compte' => $provider->user->name.' · '.$provider->user->email,
                        'Ville / quartier' => trim(($provider->city?->name ?? '—').' / '.($provider->neighborhood?->name ?? '—')),
                        'Adresse' => $provider->address ?: '—',
                        'Statut juridique' => $provider->is_formal ? 'Entreprise enregistrée' : 'Informel',
                        'RCCM / IFU' => ($provider->rccm ?: '—').' / '.($provider->ifu ?: '—'),
                        'Service de nuit' => $provider->night_service ? 'Oui' : 'Non',
                        'Livraison' => feature('delivery_enabled') ? ($provider->delivery_available ? 'Oui — '.($provider->delivery_details ?: 'sans précision') : 'Non') : 'Désactivée sur la plateforme',
                        'Paiements' => implode(', ', (array) $provider->payment_methods) ?: '—',
                        'Inscrit le' => $provider->created_at->format('d/m/Y H:i'),
                    ] + \App\Models\ProfileField::forProvider($provider)->mapWithKeys(fn ($f) => [$f->label.($f->is_public ? '' : ' (interne)') => $f->display($provider->custom_fields[$f->key] ?? null) ?? '—'])->all() + [
                    ] as $label => $value)
                        <div><dt class="text-xs text-ink-400">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($provider->description)
                    <p class="mt-4 rounded-xl bg-ink-50 p-3 text-sm whitespace-pre-line">{{ $provider->description }}</p>
                @endif
                <p class="mt-4 text-sm"><span class="text-ink-400">Services :</span> {{ $provider->services->pluck('name')->implode(', ') ?: 'aucun' }}</p>
                <p class="mt-1 text-sm"><span class="text-ink-400">Horaires :</span>
                    {{ $provider->schedules->map(fn ($s) => mb_substr($s->dayName(), 0, 3).' '.$s->label())->implode(' · ') ?: 'non renseignés' }}</p>
                @if ($provider->latitude)
                    <p class="mt-1 text-sm"><span class="text-ink-400">GPS :</span>
                        <a href="https://www.openstreetmap.org/?mlat={{ $provider->latitude }}&mlon={{ $provider->longitude }}#map=17/{{ $provider->latitude }}/{{ $provider->longitude }}" target="_blank" class="text-brand-600">{{ $provider->latitude }}, {{ $provider->longitude }} ↗</a></p>
                @endif
                @if ($provider->photos->isNotEmpty())
                    <div class="mt-4 grid grid-cols-4 gap-2 sm:grid-cols-6">
                        @foreach ($provider->photos as $photo)
                            <a href="{{ $photo->url() }}" target="_blank"><img src="{{ $photo->url() }}" alt="" class="aspect-square rounded-lg object-cover"></a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card p-5">
                <h2 class="mb-3 font-bold">Signalements ({{ $reports->count() }})</h2>
                <ul class="divide-y divide-ink-50 text-sm">
                    @forelse ($reports as $r)
                        <li class="py-2"><span class="chip bg-ink-100">{{ $r->status->label() }}</span> <strong>{{ $r->reasonLabel() }}</strong>
                            — {{ $r->details }} <span class="text-ink-400">({{ $r->reporter?->name }}, {{ $r->created_at->format('d/m/Y') }})</span></li>
                    @empty
                        <li class="py-2 text-ink-400">Aucun signalement.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="card p-5">
                <h2 class="mb-2 font-bold">Contrôles automatiques</h2>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($checks as $check)
                        <li @class(['text-emerald-700' => $check['ok'] === true, 'text-amber-700' => $check['ok'] === null, 'text-red-700' => $check['ok'] === false])>
                            {{ $check['ok'] === true ? '✓' : ($check['ok'] === null ? '⚠' : '✕') }} {{ $check['label'] }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-ink-400">Fiche complète à {{ $completion['percent'] }} %.</p>
            </section>

            <section class="card space-y-3 p-5">
                <h2 class="font-bold">Décision</h2>

                @if (in_array($provider->status, [\App\Enums\ProviderStatus::Pending, \App\Enums\ProviderStatus::Rejected]))
                    <form method="POST" action="{{ route('admin.providers.approve', $provider) }}">@csrf
                        <button class="btn btn-primary w-full">✓ Valider et publier</button>
                    </form>
                @endif

                @if ($provider->status === \App\Enums\ProviderStatus::Pending)
                    <form method="POST" action="{{ route('admin.providers.reject', $provider) }}" class="space-y-2">@csrf
                        <textarea name="reason" rows="3" class="input" placeholder="Motif du refus (envoyé au prestataire)" required>{{ old('reason') }}</textarea>
                        <button class="btn btn-danger w-full">Refuser</button>
                    </form>
                @endif

                @if ($provider->status === \App\Enums\ProviderStatus::Approved)
                    <form method="POST" action="{{ route('admin.providers.suspend', $provider) }}" class="space-y-2">@csrf
                        <textarea name="reason" rows="2" class="input" placeholder="Motif de la suspension" required></textarea>
                        <button class="btn btn-danger w-full">Suspendre</button>
                    </form>
                @endif

                @if ($provider->status === \App\Enums\ProviderStatus::Suspended)
                    <form method="POST" action="{{ route('admin.providers.reactivate', $provider) }}">@csrf
                        <button class="btn btn-primary w-full">Réactiver</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.providers.featured', $provider) }}">@csrf
                    <button class="btn btn-ghost w-full">{{ $provider->is_featured ? 'Retirer de la une' : '⭐ Mettre à la une' }}</button>
                </form>
                @unless (feature('featured_enabled'))
                    <p class="text-xs text-ink-400">La mise en avant n'a d'effet que si l'option « à la une » est activée dans les réglages.</p>
                @endunless

                @if ($provider->rejection_reason)
                    <p class="rounded-lg bg-ink-50 p-2 text-xs text-ink-600"><strong>Dernier motif :</strong> {{ $provider->rejection_reason }}</p>
                @endif
                @if ($provider->approver)
                    <p class="text-xs text-ink-400">Validé par {{ $provider->approver->name }} le {{ $provider->approved_at?->format('d/m/Y') }}</p>
                @endif
            </section>

            <section class="card p-5">
                <h2 class="mb-2 font-bold">Historique</h2>
                <ul class="space-y-1 text-xs text-ink-600">
                    @forelse ($logs as $log)
                        <li>{{ $log->created_at->format('d/m/Y H:i') }} · <code>{{ $log->action }}</code> · {{ $log->user?->name ?? 'prestataire' }}</li>
                    @empty
                        <li class="text-ink-400">Aucune action.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.admin>
