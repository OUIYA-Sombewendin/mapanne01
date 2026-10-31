@props(['title' => null])
@php
    $me = auth()->user();
    // Compteurs « à traiter » (quelques requêtes COUNT, mises en cache 60 s).
    $todo = cache()->remember('admin.todo-counts', 60, fn () => [
        'providers' => \App\Models\Provider::where('status', \App\Enums\ProviderStatus::Pending)->count(),
        'review' => \App\Models\Provider::where('needs_review', true)->count(),
        'reports' => \App\Models\Report::where('status', \App\Enums\ReportStatus::Open)->count(),
        'reviews' => \App\Models\Review::where('is_pending', true)->count(),
        'products' => \App\Models\Product::whereNull('approved_at')->count(),
        'subscriptions' => \App\Models\Subscription::where('status', 'pending')->count(),
    ]);
    $typeParam = request()->routeIs('admin.providers.*') ? request('type') : null;

    // [route, paramètres, actif ?, icône, libellé, compteur]. Une entrée n'apparaît que si la permission est accordée.
    $sections = [
        'Général' => [
            ['admin.dashboard', [], request()->routeIs('admin.dashboard'), 'dashboard', 'Tableau de bord', null, true],
            ['admin.history.index', [], request()->routeIs('admin.history.*'), 'history', 'Historique', null, $me->can('history.view')],
        ],
        'Prestataires' => array_merge(
            [['admin.providers.index', [], request()->routeIs('admin.providers.*') && ! $typeParam, 'providers', 'Inscriptions et fiches', $todo['providers'] + $todo['review'], $me->can('providers.view')]],
            \App\Models\ProviderType::cached()->where('is_active', true)->values()->map(fn ($t) => [
                'admin.providers.index', ['type' => $t->slug, 'status' => 'all'], $typeParam === $t->slug, 'emoji:'.$t->icon(), $t->shortLabel(), null, $me->can('providers.view'),
            ])->all(),
        ),
        'Marketplace et modération' => [
            ['admin.marketplace.index', [], request()->routeIs('admin.marketplace.*'), 'shop', 'Pièces et demandes', $todo['products'], $me->can('marketplace.manage')],
            ['admin.reports.index', [], request()->routeIs('admin.reports.*'), 'flag', 'Signalements', $todo['reports'], $me->can('moderation.manage')],
            ['admin.reviews.index', [], request()->routeIs('admin.reviews.*'), 'star', 'Avis', $todo['reviews'], $me->can('moderation.manage')],
        ],
        'Abonnements' => [
            ['admin.subscriptions.index', [], request()->routeIs('admin.subscriptions.*'), 'card', 'Abonnements', $todo['subscriptions'], $me->can('subscriptions.manage')],
            ['admin.plans.index', [], request()->routeIs('admin.plans.*'), 'layers', 'Offres et fonctionnalités', null, $me->can('subscriptions.manage')],
        ],
        'Utilisateurs' => [
            ['admin.users.index', [], request()->routeIs('admin.users.*'), 'users', 'Comptes', null, $me->can('users.view')],
            ['admin.roles.index', [], request()->routeIs('admin.roles.*'), 'shield', 'Rôles et permissions', null, $me->can('roles.manage')],
        ],
        'Référentiels' => [
            ['admin.types.index', [], request()->routeIs('admin.types.*'), 'tag', 'Types de prestataires', null, $me->can('referentials.manage')],
            ['admin.forms.index', [], request()->routeIs('admin.forms.*'), 'document', 'Formulaires des métiers', null, $me->can('referentials.manage')],
            ['admin.services.index', [], request()->routeIs('admin.services.*'), 'wrench', 'Services', null, $me->can('referentials.manage')],
            ['admin.locations.index', [], request()->routeIs('admin.locations.*'), 'map', 'Villes et quartiers', null, $me->can('referentials.manage')],
            ['admin.pages.index', [], request()->routeIs('admin.pages.*'), 'document', 'Pages de contenu', null, $me->can('content.manage')],
        ],
        'Configuration' => [
            ['admin.settings.index', ['group' => 'appearance'], request()->routeIs('admin.settings.*') && in_array(request()->route('group'), ['appearance', 'home'], true), 'palette', 'Apparence et accueil', null, $me->can('appearance.manage')],
            ['admin.settings.index', [], request()->routeIs('admin.settings.*') && ! in_array(request()->route('group'), ['appearance', 'home', 'backups'], true), 'settings', 'Réglages et options', null, $me->can('settings.manage')],
            ['admin.backups.index', [], request()->routeIs('admin.backups.*') || request()->route('group') === 'backups', 'database', 'Sauvegardes', null, $me->can('backups.manage')],
            ['admin.data.index', [], request()->routeIs('admin.data.*'), 'transfer', 'Import / export', null, $me->can('data.export') || $me->can('data.import')],
            ['admin.locks.index', [], request()->routeIs('admin.locks.*'), 'shield', 'Verrous', null, $me->can('locks.manage')],
        ],
    ];
    $sections = array_filter(array_map(fn ($links) => array_values(array_filter($links, fn ($l) => $l[6])), $sections));
    // Format commun du menu latéral : [url, actif, icône, libellé, compteur]
    $navSections = array_map(fn ($links) => array_map(fn ($l) => [route($l[0], $l[1]), $l[2], $l[3], $l[4], $l[5]], $links), $sections);
    $notifications = array_filter([
        ['Inscriptions à valider', $todo['providers'], $me->can('providers.view') ? route('admin.providers.index') : null],
        ['Changements de fiche à vérifier', $todo['review'], $me->can('providers.view') ? route('admin.providers.index', ['status' => 'review']) : null],
        ['Signalements ouverts', $todo['reports'], $me->can('moderation.manage') ? route('admin.reports.index') : null],
        ['Avis en attente', $todo['reviews'], $me->can('moderation.manage') ? route('admin.reviews.index', ['filter' => 'pending']) : null],
        ['Produits en attente', $todo['products'], $me->can('marketplace.manage') ? route('admin.marketplace.index', ['filter' => 'pending']) : null],
        ['Abonnements à activer', $todo['subscriptions'], $me->can('subscriptions.manage') ? route('admin.subscriptions.index') : null],
    ], fn ($n) => $n[1] > 0 && $n[2]);
    $notifCount = array_sum(array_column($notifications, 1));
@endphp
<!DOCTYPE html>
<html lang="fr" x-data="adminShell" :class="{ 'admin-dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title ? $title.' · ' : '' }}Admin {{ setting('site_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    {{-- Thème sombre appliqué avant l'affichage (évite le flash blanc). --}}
    <script>try { if (localStorage.getItem('mp-admin-dark') === '1') document.documentElement.classList.add('admin-dark') } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeCss) <style>{!! $themeCss !!}</style> @endif
</head>
<body class="app-shell min-h-dvh bg-ink-50">
    <div class="lg:grid lg:transition-[grid-template-columns]" :class="collapsed ? 'lg:grid-cols-[84px_minmax(0,1fr)]' : 'lg:grid-cols-[288px_minmax(0,1fr)]'">
        {{-- Menu latéral : tiroir sur mobile, fixe et réductible sur ordinateur --}}
        <div x-cloak x-show="menu" @click="menu = false" class="fixed inset-0 z-40 bg-black/40 lg:hidden"></div>
        <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-ink-100 bg-white transition lg:sticky lg:top-0 lg:h-dvh lg:w-auto lg:translate-x-0">
            <a href="{{ route('admin.dashboard') }}" class="flex h-[84px] shrink-0 items-center gap-3 border-b border-ink-100 px-6" :class="collapsed && 'lg:justify-center lg:px-0'">
                <span class="on-color flex size-12 shrink-0 items-center justify-center rounded-2xl bg-ink-800 shadow-sm"><x-logo class="size-8" /></span>
                <span class="text-xl font-extrabold tracking-tight" :class="collapsed && 'lg:hidden'">{{ \Illuminate\Support\Str::of(setting('site_name'))->substr(0, 2) }}<span class="text-brand-500">{{ \Illuminate\Support\Str::of(setting('site_name'))->substr(2) }}</span></span>
            </a>

            <x-side-nav :sections="$navSections" :icons="[
                'Prestataires' => 'providers', 'Marketplace et modération' => 'shop', 'Abonnements' => 'card',
                'Utilisateurs' => 'users', 'Référentiels' => 'tag', 'Configuration' => 'settings',
            ]" />

            <button @click="toggleCollapse()" class="hidden h-14 shrink-0 items-center justify-center gap-2 border-t border-ink-100 text-sm text-ink-400 hover:text-ink-700 lg:flex">
                <x-icon name="collapse" class="size-4" /><span x-text="collapsed ? '' : 'Réduire'">Réduire</span>
            </button>
        </aside>

        <div class="min-w-0">
            {{-- Barre supérieure --}}
            <header class="sticky top-0 z-30 flex h-[84px] items-center gap-3 border-b border-ink-100 bg-white/95 px-4 backdrop-blur lg:px-8">
                <button @click="window.innerWidth < 1024 ? menu = true : toggleCollapse()" class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-ink-100 text-ink-600 hover:bg-ink-50" aria-label="Menu">
                    <x-icon name="sidebar" />
                </button>
                <form action="{{ route('admin.search') }}" class="relative hidden max-w-xl flex-1 sm:block">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-400" />
                    <input name="q" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" class="h-11 w-full rounded-xl border border-ink-100 bg-ink-50 pr-4 pl-12 text-[15px] placeholder:text-ink-400 focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100 focus:outline-none" placeholder="Rechercher un prestataire, un utilisateur, une pièce…">
                </form>

                <div class="ml-auto flex items-center gap-2 sm:gap-3">
                    <a href="{{ route('admin.search') }}" class="flex size-11 items-center justify-center rounded-xl border border-ink-100 text-ink-600 sm:hidden" aria-label="Rechercher"><x-icon name="search" /></a>
                    <button @click="toggleDark()" class="flex size-11 items-center justify-center rounded-xl border border-ink-100 text-ink-600 hover:bg-ink-50" :aria-label="dark ? 'Mode clair' : 'Mode sombre'">
                        <x-icon name="moon" x-show="!dark" /><x-icon name="sun" x-show="dark" x-cloak />
                    </button>

                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.outside="open = false" class="relative flex size-11 items-center justify-center rounded-xl border border-ink-100 text-ink-600 hover:bg-ink-50" aria-label="Notifications">
                            <x-icon name="bell" />
                            @if ($notifCount)
                                <span class="on-color absolute -top-1 -right-1 min-w-5 rounded-full bg-brand-500 px-1 text-center text-[11px] leading-5 font-bold text-white">{{ $notifCount > 99 ? '99+' : $notifCount }}</span>
                            @endif
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-72 overflow-hidden rounded-2xl bg-white text-sm shadow-xl ring-1 ring-ink-100">
                            <p class="border-b border-ink-50 px-4 py-3 font-semibold">À traiter</p>
                            @forelse ($notifications as [$label, $count, $url])
                                <a href="{{ $url }}" class="flex items-center justify-between px-4 py-2.5 hover:bg-ink-50"><span>{{ $label }}</span><span class="on-color rounded-full bg-brand-500 px-2 text-xs font-bold text-white">{{ $count }}</span></a>
                            @empty
                                <p class="px-4 py-4 text-ink-400">Rien à traiter. 👌</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.outside="open = false" class="flex h-11 items-center gap-2 rounded-xl border border-ink-100 py-1 pr-3 pl-1 hover:bg-ink-50">
                            <span class="on-color flex size-9 items-center justify-center rounded-full bg-ink-800 text-xs font-bold text-white">{{ $me->initials() }}</span>
                            <span class="hidden max-w-40 truncate text-[15px] font-medium uppercase md:block">{{ $me->name }}</span>
                            <x-icon name="chevron" class="size-4 text-ink-400" />
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-60 overflow-hidden rounded-2xl bg-white py-1 text-sm shadow-xl ring-1 ring-ink-100">
                            <div class="border-b border-ink-50 px-4 py-3">
                                <p class="font-semibold">{{ $me->name }}</p>
                                <p class="text-xs text-ink-400">{{ $me->adminRole?->name ?? 'Administrateur' }}</p>
                            </div>
                            <a href="{{ route('account.show') }}" class="flex items-center gap-2 px-4 py-2.5 hover:bg-ink-50"><x-icon name="users" class="size-4" />Mon compte</a>
                            <a href="{{ route('home') }}" class="flex items-center gap-2 px-4 py-2.5 hover:bg-ink-50"><x-icon name="external" class="size-4" />Voir le site</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="flex w-full items-center gap-2 px-4 py-2.5 text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" />Se déconnecter</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 lg:px-8 lg:py-8">
                @if (session('success'))
                    <div class="on-color mb-4 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="on-color mb-4 rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="on-color mb-4 rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white">{{ $errors->first() }}</div>
                @endif
                @if (session('importErrors'))
                    <details class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <summary class="cursor-pointer font-semibold">{{ count(session('importErrors')) }} ligne(s) ignorée(s)</summary>
                        <ul class="mt-2 list-disc pl-5">@foreach (array_slice(session('importErrors'), 0, 50) as $err)<li>{{ $err }}</li>@endforeach</ul>
                    </details>
                @endif
                @if (feature('maintenance_enabled'))
                    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        ⚠️ Le mode maintenance est activé : le site est fermé au public.
                        @can('settings.manage') <a href="{{ route('admin.settings.index', 'general') }}" class="font-semibold underline">Désactiver</a> @endcan
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

</body>
</html>
