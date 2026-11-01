@php
    $me = auth()->user();
    $money = fn ($v) => number_format((int) $v, 0, ',', ' ').' F';
    $role = $me->shopRole($provider);
@endphp
<x-layouts.pro title="Tableau de bord">
    {{-- Bandeau d'accueil --}}
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-lg sm:px-10 sm:py-9">
        <div class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/5"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="mb-2 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium">{{ $provider->type->icon() }} {{ $provider->type->label() }} · {{ \App\Support\ShopTeam::label($role) }}</p>
                <h1 class="text-2xl font-extrabold sm:text-4xl">{{ $provider->business_name }}</h1>
                <span class="mt-3 block h-1 w-28 rounded-full bg-brand-500"></span>
                <p class="mt-4 text-white/80 sm:text-lg">
                    Bonjour {{ \Illuminate\Support\Str::of($me->name)->before(' ') }}
                    @if ($previousLogin) — dernière connexion le {{ $previousLogin->format('d/m/Y') }} à {{ $previousLogin->format('H:i') }} @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if ($kpis['salesToday'] !== null)
                    <a href="{{ route('pro.manage.invoices.create') }}" class="btn bg-white px-5 py-3 text-base text-ink-900 shadow hover:bg-white/90"><x-icon name="plus" /> Nouvelle vente</a>
                @endif
                @if ($provider->canSellParts() && $me->canShop('catalog'))
                    <a href="{{ route('pro.products.create') }}" class="btn border border-white/30 px-5 py-3 text-base text-white hover:bg-white/10"><x-icon name="cube" /> Ajouter une pièce</a>
                @endif
                <a href="{{ route('providers.show', $provider) }}" target="_blank" class="btn border border-white/30 px-5 py-3 text-base text-white hover:bg-white/10"><x-icon name="external" /> Ma page</a>
            </div>
        </div>
    </section>

    {{-- Statut de validation --}}
    <div class="mt-6">
        @switch($provider->status)
            @case(\App\Enums\ProviderStatus::Pending)
                <div class="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                    <span class="text-xl">⏳</span>
                    <div><p class="font-semibold">Statut : En attente de validation</p>
                        <p class="text-sm">L'équipe {{ setting('site_name') }} vérifie vos informations : la fiche n'apparaît pas encore dans les recherches. Une fiche complète est validée plus vite.</p></div>
                </div>
                @break
            @case(\App\Enums\ProviderStatus::Rejected)
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900">
                    <p class="font-semibold">Statut : Refusée</p>
                    <p class="mt-1 text-sm"><strong>Motif :</strong> {{ $provider->rejection_reason }}</p>
                    @if ($me->canShop('profile'))
                        <form method="POST" action="{{ route('pro.resubmit') }}" class="mt-3">@csrf
                            <button class="btn btn-danger btn-sm">J'ai corrigé, renvoyer en vérification</button>
                        </form>
                    @endif
                </div>
                @break
            @case(\App\Enums\ProviderStatus::Suspended)
                <div class="rounded-2xl border border-ink-200 bg-ink-100 p-4">
                    <p class="font-semibold">Statut : Bloquée (suspendue)</p>
                    <p class="mt-1 text-sm text-ink-600">{{ $provider->rejection_reason }} — contactez le support {{ setting('site_name') }}.</p>
                </div>
                @break
            @default
                <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <span class="on-color flex size-6 items-center justify-center rounded-full bg-emerald-600 text-white">✓</span>
                    <span>Statut : <strong>Approuvée</strong>. La fiche est vérifiée et visible par les clients{{ $provider->approved_at ? ' depuis le '.$provider->approved_at->format('d/m/Y') : '' }}.</span>
                </div>
        @endswitch
    </div>

    {{-- Cartes de couleur --}}
    @php
        $cards = array_values(array_filter([
            $kpis['month'] ? ['chart', $money($kpis['month']['revenue']), 'Ventes du mois', $kpis['month']['invoices'].' facture(s)', 'admin-hero', route('pro.manage.reports')] : null,
            $kpis['month'] ? ['card', $money($kpis['month']['net']), 'Bénéfice net du mois', 'marge − dépenses', $kpis['month']['net'] >= 0 ? 'bg-gradient-to-br from-emerald-600 to-emerald-800' : 'bg-gradient-to-br from-rose-500 to-rose-700', route('pro.manage.index')] : null,
            ! $kpis['month'] && $kpis['salesToday'] !== null ? ['card', $money($kpis['salesToday']), 'Ventes du jour', 'factures du jour', 'admin-hero', route('pro.manage.invoices.index')] : null,
            $kpis['products'] !== null ? ['cube', $kpis['products'], 'Pièces au catalogue', $kpis['lowStock'].' à réapprovisionner', 'bg-gradient-to-br from-sky-600 to-blue-800', $me->canShop('stock') ? route('pro.manage.stock') : null] : null,
            $kpis['visits'] ? ['chart', number_format($kpis['visits']['views'], 0, ',', ' '), 'Vues de la fiche (7 jours)', ($kpis['visits']['calls'] + $kpis['visits']['whatsapp'] + $kpis['visits']['directions']).' contact(s) · '.$kpis['visits']['directions'].' itinéraire(s)', 'bg-gradient-to-br from-violet-600 to-indigo-800', $provider->hasPlanFeature('statistics') ? route('pro.stats') : null] : null,
            ['star', number_format($provider->rating_avg, 1, ',', ''), 'Note moyenne', $provider->reviews_count.' avis · '.$favoritesCount.' favoris', 'bg-gradient-to-br from-amber-500 to-orange-700', null],
        ]));
    @endphp
    <div @class(['mt-6 grid gap-4 sm:grid-cols-2', 'xl:grid-cols-4' => count($cards) >= 4, 'xl:grid-cols-3' => count($cards) === 3])>
        @foreach ($cards as [$icon, $value, $label, $hint, $bg, $url])
            <a @if ($url) href="{{ $url }}" @endif class="on-color relative overflow-hidden rounded-2xl {{ $bg }} p-6 text-white shadow-md transition hover:-translate-y-0.5">
                <span class="pointer-events-none absolute -right-10 -bottom-12 size-40 rounded-full bg-white/10"></span>
                <x-icon :name="$icon" class="size-8" />
                <p class="mt-4 text-3xl font-extrabold sm:text-4xl">{{ $value }}</p>
                <p class="mt-1 text-lg font-bold">{{ $label }}</p>
                <p class="text-sm text-white/75">{{ $hint }}</p>
            </a>
        @endforeach
    </div>

    {{-- Indicateurs secondaires --}}
    @php
        $tiles = array_values(array_filter([
            $kpis['salesToday'] !== null ? ['card', $money($kpis['salesToday']), 'Ventes du jour', 'border-accent-600', 'bg-accent-50 text-accent-700'] : null,
            $kpis['unpaid'] !== null ? ['clock', $money($kpis['unpaid']), 'Reste à encaisser', 'border-brand-500', 'bg-brand-50 text-brand-600'] : null,
            $kpis['views'] !== null ? ['search', number_format($kpis['views'], 0, ',', ' '), 'Vues des pièces', 'border-accent-600', 'bg-accent-50 text-accent-700'] : null,
            $kpis['contacts'] !== null ? ['phone', number_format($kpis['contacts'], 0, ',', ' '), 'Contacts reçus', 'border-brand-500', 'bg-brand-50 text-brand-600'] : null,
        ]));
    @endphp
    @if ($tiles)
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($tiles as [$icon, $value, $label, $border, $tile])
                <div class="card border-t-4 {{ $border }} p-5 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-2xl {{ $tile }}"><x-icon :name="$icon" class="size-6" /></span>
                    <p class="mt-3 text-2xl font-extrabold">{{ $value }}</p>
                    <p class="text-sm font-semibold">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
        {{-- Disponibilité instantanée --}}
        <section class="card p-5 xl:col-span-2">
            <h2 class="font-bold">Je suis…</h2>
            <p class="mb-3 text-sm text-ink-500">Statut affiché aux clients. {{ $isOpen ? 'Vous êtes dans vos horaires d\'ouverture.' : 'Vous êtes hors de vos horaires d\'ouverture.' }}</p>
            <form method="POST" action="{{ route('pro.availability') }}" class="grid grid-cols-3 gap-2" @unless ($me->canShop('availability')) inert style="opacity:.6" @endunless>
                @csrf @method('PUT')
                @foreach (\App\Enums\Availability::cases() as $a)
                    <button name="availability" value="{{ $a->value }}" @class([
                        'flex flex-col items-center gap-1 rounded-xl border-2 p-3 text-sm font-semibold transition',
                        'on-color border-ink-800 bg-ink-800 text-white' => $provider->availability === $a,
                        'border-ink-100 hover:border-ink-300' => $provider->availability !== $a,
                    ])>
                        <span class="size-3 rounded-full {{ $a->dot() }}"></span>{{ $a->label() }}
                    </button>
                @endforeach
            </form>
        </section>

        {{-- Complétude de la fiche --}}
        <section class="card p-5">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-bold">Fiche complète à</h2>
                <span class="text-lg font-extrabold text-brand-600">{{ $completion['percent'] }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-ink-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $completion['percent'] }}%"></div></div>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($completion['checks'] as $label => $ok)
                    <li class="{{ $ok ? 'text-ink-400 line-through' : 'text-ink-700' }}">{{ $ok ? '✓' : '○' }} {{ $label }}</li>
                @endforeach
            </ul>
            @if ($me->canShop('profile') && $completion['percent'] < 100)
                <a href="{{ route('pro.profile') }}" class="btn btn-ghost btn-sm mt-3 w-full">Compléter la fiche</a>
            @endif
        </section>

        {{-- Dernières ventes --}}
        @if ($recentInvoices->isNotEmpty())
            <section class="card p-5 xl:col-span-2">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-bold">Dernières ventes</h2>
                    <a href="{{ route('pro.manage.invoices.index') }}" class="text-sm text-brand-600">Tout voir</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Par</th><th class="text-right">Total</th><th>Statut</th></tr></thead>
                        <tbody>
                            @foreach ($recentInvoices as $inv)
                                <tr>
                                    <td><a href="{{ route('pro.manage.invoices.show', $inv) }}" class="font-semibold text-brand-600">{{ $inv->number }}</a></td>
                                    <td class="whitespace-nowrap">{{ $inv->issued_at->format('d/m/Y') }}</td>
                                    <td>{{ $inv->customer_name ?: '—' }}</td>
                                    <td class="text-ink-500">{{ $inv->seller?->name ?? '—' }}</td>
                                    <td class="text-right font-semibold whitespace-nowrap">{{ $money($inv->total) }}</td>
                                    <td><span class="chip {{ $inv->statusClass() }}">{{ $inv->statusLabel() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Derniers avis --}}
        <section @class(['card p-5', 'xl:col-span-2' => $recentInvoices->isEmpty()])>
            <h2 class="mb-2 font-bold">Derniers avis</h2>
            <ul class="divide-y divide-ink-50">
                @forelse ($latestReviews as $review)
                    <li class="py-2 text-sm">
                        <span class="text-amber-500">{{ str_repeat('★', $review->rating) }}</span>
                        <span class="font-medium">{{ \Illuminate\Support\Str::of($review->user->name)->before(' ') }}</span>
                        <span class="text-ink-400">· {{ $review->created_at->diffForHumans() }}</span>
                        @if ($review->comment) <p class="text-ink-600">{{ $review->comment }}</p> @endif
                    </li>
                @empty
                    <li class="py-2 text-sm text-ink-400">Pas encore d'avis. Invitez vos clients satisfaits à noter votre activité sur {{ setting('site_name') }}.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.pro>
