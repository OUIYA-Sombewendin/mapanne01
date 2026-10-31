@php
    $me = auth()->user();
    // Couleurs des cartes « par type » (dans l'ordre des types).
    $palette = [
        'from-emerald-600 to-emerald-800', 'from-sky-600 to-blue-800', 'from-amber-500 to-orange-700',
        'from-violet-600 to-purple-800', 'from-rose-500 to-rose-700', 'from-teal-500 to-cyan-700',
    ];
    $totalApproved = $typeCounts->sum();
@endphp
<x-layouts.admin title="Tableau de bord">
    {{-- Bandeau d'accueil --}}
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-lg sm:px-10 sm:py-9">
        <div class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/5"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="flex items-center gap-3 text-2xl font-extrabold sm:text-4xl"><x-icon name="dashboard" class="size-9 sm:size-11" stroke-width="1.5" /> Tableau de bord {{ setting('site_name') }}</h1>
                <span class="mt-3 block h-1 w-28 rounded-full bg-brand-500"></span>
                <p class="mt-4 text-white/80 sm:text-lg">
                    Bonjour {{ $me->name }}
                    @if ($previousLogin) — dernière connexion le {{ $previousLogin->format('d/m/Y') }} à {{ $previousLogin->format('H:i') }} @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                @can('providers.view')
                    <a href="{{ route('admin.providers.index') }}" class="btn bg-white px-5 py-3 text-base text-ink-900 shadow hover:bg-white/90"><x-icon name="plus" /> Valider les inscriptions @if ($stats['providers_pending']) ({{ $stats['providers_pending'] }}) @endif</a>
                @endcan
                @can('history.view')
                    <a href="{{ route('admin.history.index') }}" class="btn border border-white/30 px-5 py-3 text-base text-white hover:bg-white/10"><x-icon name="chart" /> Historique</a>
                @endcan
            </div>
        </div>
    </section>

    {{-- Prestataires par type --}}
    <div class="relative mt-8 mb-5 flex flex-wrap items-center gap-3 overflow-hidden rounded-2xl px-5 py-3 ring-1" style="background: var(--section-bg); --tw-ring-color: var(--tint)">
        <span class="absolute top-2.5 bottom-2.5 left-0 w-1 rounded-r bg-brand-500"></span>
        <span class="flex size-11 items-center justify-center rounded-xl bg-white text-accent-600 shadow-sm"><x-icon name="providers" class="size-6" /></span>
        <h2 class="text-xl font-extrabold" style="color: var(--heading)">Prestataires par type</h2>
        <span class="text-ink-500">Fiches validées et visibles du public</span>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <a href="{{ route('admin.providers.index', ['status' => 'approved']) }}" class="admin-hero on-color relative overflow-hidden rounded-2xl p-6 text-white shadow-md transition hover:-translate-y-0.5">
            <span class="pointer-events-none absolute -right-10 -bottom-12 size-40 rounded-full bg-white/10"></span>
            <x-icon name="users" class="size-8" />
            <p class="mt-4 text-5xl font-extrabold">{{ $totalApproved }}</p>
            <p class="mt-1 text-lg font-bold">Au total</p>
            <p class="text-sm text-white/75">{{ $stats['providers_pending'] }} en attente de validation</p>
        </a>
        @foreach ($types as $i => $type)
            <a href="{{ route('admin.providers.index', ['type' => $type->slug, 'status' => 'all']) }}" class="on-color relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $palette[$i % count($palette)] }} p-6 text-white shadow-md transition hover:-translate-y-0.5">
                <span class="pointer-events-none absolute -right-10 -bottom-12 size-40 rounded-full bg-white/10"></span>
                <span class="flex size-9 items-center justify-center rounded-full bg-white/90 text-lg">{{ $type->icon() }}</span>
                <p class="mt-4 text-5xl font-extrabold">{{ (int) ($typeCounts[$type->slug] ?? 0) }}</p>
                <p class="mt-1 text-lg font-bold">{{ $type->shortLabel() }}</p>
                <p class="text-sm text-white/75">{{ $type->can_sell_parts ? 'peut vendre des pièces' : 'validés' }}</p>
            </a>
        @endforeach
    </div>

    {{-- Indicateurs --}}
    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['users', $stats['clients'], 'Clients', '+'.$stats['new_users_7d'].' en 7 jours', 'border-accent-600', 'bg-accent-50 text-accent-700'],
            ['cube', $stats['products'], 'Pièces en vente', $stats['open_requests'].' demande(s) ouvertes', 'border-brand-500', 'bg-brand-50 text-brand-600'],
            ['phone', $stats['contacts'], 'Contacts générés', 'appels et WhatsApp', 'border-accent-600', 'bg-accent-50 text-accent-700'],
            ['star', $stats['reviews'], 'Avis publiés', 'tous prestataires', 'border-brand-500', 'bg-brand-50 text-brand-600'],
            ['card', $stats['subscriptions'], 'Abonnements actifs', number_format($stats['subscriptions_revenue'], 0, ',', ' ').' F ce mois', 'border-accent-600', 'bg-accent-50 text-accent-700'],
            ['clock', $stats['avg_validation_hours'] !== null ? $stats['avg_validation_hours'].' h' : '—', 'Délai de validation', 'moyenne sur 30 jours', 'border-brand-500', 'bg-brand-50 text-brand-600'],
        ] as [$icon, $value, $label, $hint, $border, $tile])
            <div class="card border-t-4 {{ $border }} p-5 text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-2xl {{ $tile }}"><x-icon :name="$icon" class="size-7" /></span>
                <p class="mt-3 text-3xl font-extrabold">{{ $value }}</p>
                <p class="font-semibold">{{ $label }}</p>
                <p class="text-xs text-ink-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
        <section class="card p-5">
            <h2 class="mb-3 font-bold">À traiter</h2>
            <ul class="space-y-1">
                @foreach ($todo as [$label, $count, $url])
                    <li><a href="{{ $url }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-ink-50">
                        <span>{{ $label }}</span>
                        <span @class(['rounded-full px-2.5 text-xs font-bold', 'on-color bg-brand-500 text-white' => $count, 'bg-ink-100 text-ink-400' => ! $count])>{{ $count }}</span>
                    </a></li>
                @endforeach
            </ul>
        </section>

        <section class="card p-5 xl:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-bold">Inscriptions en attente</h2>
                @can('providers.view') <a href="{{ route('admin.providers.index') }}" class="text-sm text-brand-600">Tout voir</a> @endcan
            </div>
            <ul class="divide-y divide-ink-50">
                @forelse ($pending as $p)
                    <li class="flex items-center justify-between gap-2 py-2.5 text-sm">
                        <div>
                            <p class="font-semibold">{{ $p->type->icon() }} {{ $p->business_name }}</p>
                            <p class="text-xs text-ink-400">{{ $p->type->label() }} · {{ $p->city?->name }} · inscrit {{ $p->created_at->diffForHumans() }}</p>
                        </div>
                        @can('providers.view') <a href="{{ route('admin.providers.show', $p) }}" class="btn btn-dark btn-sm">Examiner</a> @endcan
                    </li>
                @empty
                    <li class="py-3 text-sm text-ink-400">Aucune inscription en attente.</li>
                @endforelse
            </ul>
        </section>

        @can('history.view')
            <section class="card p-5 xl:col-span-3">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Dernières actions</h2>
                    <a href="{{ route('admin.history.index') }}" class="text-sm text-brand-600">Historique complet</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Par</th><th>Action</th><th>Objet</th></tr></thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="whitespace-nowrap text-ink-500">{{ $log->created_at->format('d/m H:i') }}</td>
                                    <td>{{ $log->user?->name ?? 'Système' }}</td>
                                    <td>{{ $log->label() }}</td>
                                    <td class="text-ink-500">{{ $log->subjectLabel() }}
                                        @if (! empty($log->details['reason'])) — {{ \Illuminate\Support\Str::limit($log->details['reason'], 60) }} @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-ink-400">Aucune action enregistrée.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endcan
    </div>
</x-layouts.admin>
