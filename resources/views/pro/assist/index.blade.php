@php
    $distance = fn ($m) => $m === null ? null : ($m < 1000 ? $m.' m' : number_format($m / 1000, 1, ',', '').' km');
@endphp
<x-layouts.pro title="Dépannages">
    <div x-data="assistWatcher(@js(route('pro.assist.count')), {{ $open->count() }})">
        <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-6 text-white shadow-lg sm:px-8">
            <div class="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-white/5"></div>
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold sm:text-3xl">🆘 Dépannage en direct</h1>
                    <p class="mt-1 text-white/80">Les conducteurs en panne près de vous. Le premier qui accepte intervient.</p>
                    <p class="mt-2 text-sm text-white/70">{{ $doneCount }} intervention(s) réalisée(s) grâce à {{ setting('site_name') }}</p>
                </div>
                <x-push-toggle class="shrink-0" />
            </div>
        </section>

        @if (auth()->user()->provider->availability === \App\Enums\Availability::Offline)
            <p class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900">Vous êtes <strong>hors ligne</strong> : vous ne recevez pas de nouvelles demandes. Changez votre disponibilité depuis le tableau de bord.</p>
        @endif

        {{-- Interventions en cours --}}
        @if ($mine->isNotEmpty())
            <h2 class="mt-8 mb-3 text-xl font-extrabold" style="color: var(--heading)">Mes interventions en cours</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($mine as $a)
                    <article class="card border-l-4 border-accent-600 p-5">
                        <div class="flex items-start gap-3">
                            <span class="text-3xl">{{ $a->problemIcon() }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-extrabold">{{ $a->problemLabel() }} <span class="font-normal text-ink-500">· {{ $a->vehicleLabel() }}</span></p>
                                <p class="text-sm text-ink-600">{{ $a->contact_name }} · <a href="tel:{{ $a->contact_phone }}" class="font-bold text-accent-700">{{ \App\Support\Phone::format($a->contact_phone) }}</a></p>
                                @if ($a->landmark) <p class="text-sm text-ink-500">📍 {{ $a->landmark }}</p> @endif
                                @if ($a->description) <p class="mt-1 text-sm text-ink-600">« {{ $a->description }} »</p> @endif
                            </div>
                            <span class="chip {{ $a->statusClass() }}">{{ $a->statusLabel() }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="tel:{{ $a->contact_phone }}" class="btn btn-primary btn-sm"><x-icon name="phone" /> Appeler</a>
                            <a href="{{ $a->mapUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><x-icon name="map" /> Itinéraire</a>
                            <form method="POST" action="{{ route('pro.assist.update', $a) }}" class="contents">@csrf
                                @if ($a->status === 'accepted')
                                    <button name="step" value="on_way" class="btn btn-dark btn-sm">Je suis en route</button>
                                @endif
                                <button name="step" value="done" class="btn btn-ghost btn-sm text-emerald-700">✓ Terminé</button>
                                <button name="step" value="release" class="btn btn-ghost btn-sm text-red-700" onclick="return confirm('Rendre cette demande à un autre prestataire ?')">Je ne peux pas venir</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        {{-- Demandes en attente --}}
        <h2 class="mt-8 mb-3 flex items-center gap-3 text-xl font-extrabold" style="color: var(--heading)">
            Demandes en attente
            <span @class(['rounded-full px-2.5 text-sm', 'on-color bg-red-600 text-white' => $open->isNotEmpty(), 'bg-ink-100 text-ink-500' => $open->isEmpty()])>{{ $open->count() }}</span>
        </h2>
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($open as $a)
                @php($m = $a->notifiedProviders->first()?->pivot->distance_m)
                <article class="card p-5">
                    <div class="flex items-start gap-3">
                        <span class="text-3xl">{{ $a->problemIcon() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-extrabold">{{ $a->problemLabel() }}</p>
                            <p class="text-sm text-ink-600">{{ $a->vehicleLabel() }} · il y a {{ $a->created_at->diffForHumans(null, true) }}</p>
                            @if ($a->description) <p class="mt-1 text-sm text-ink-600">« {{ $a->description }} »</p> @endif
                        </div>
                        @if ($m !== null) <span class="rounded-xl bg-accent-50 px-3 py-1.5 text-lg font-extrabold text-accent-800">{{ $distance($m) }}</span> @endif
                    </div>
                    <form method="POST" action="{{ route('pro.assist.accept', $a) }}" class="mt-4">@csrf
                        <button class="btn btn-primary w-full">J'accepte, j'y vais</button>
                    </form>
                    <p class="mt-2 text-center text-xs text-ink-400">Le numéro du conducteur s'affiche quand vous acceptez.</p>
                </article>
            @empty
                <p class="card p-6 text-center text-ink-500 lg:col-span-2">Aucune demande pour le moment. Cette page se met à jour toute seule ; activez les notifications pour être prévenu même quand elle est fermée.</p>
            @endforelse
        </div>

        {{-- Historique --}}
        @if ($history->isNotEmpty())
            <h2 class="mt-8 mb-3 text-xl font-extrabold" style="color: var(--heading)">Dernières interventions</h2>
            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Problème</th><th>Conducteur</th><th>Statut</th></tr></thead>
                    <tbody>
                        @foreach ($history as $a)
                            <tr>
                                <td class="whitespace-nowrap text-ink-500">{{ $a->created_at->format('d/m H:i') }}</td>
                                <td>{{ $a->problemIcon() }} {{ $a->problemLabel() }}</td>
                                <td>{{ $a->contact_name }}</td>
                                <td><span class="chip {{ $a->statusClass() }}">{{ $a->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.pro>
