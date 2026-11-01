{{--
    Espace client : une fois connecté, le client retrouve tout depuis ce cadre (menu latéral sur ordinateur,
    barre du bas sur téléphone), y compris la recherche de pros, de pièces et de stations.
    Utilisé automatiquement par x-layouts.app quand un client est connecté.
--}}
@props(['title' => null, 'description' => null, 'wide' => false])
@php
    $me = auth()->user();
    $alerts = \App\Support\ClientSpace::alerts($me);
    $is = fn (...$patterns) => request()->routeIs(...$patterns);
    $market = feature('marketplace_enabled');
    $requestsOn = $market && feature('part_requests_enabled');
    $offers = collect($alerts)->filter(fn ($a) => str_starts_with($a[0], '📣'))->count();
    $due = collect($alerts)->filter(fn ($a) => in_array($a[3], ['amber', 'red'], true) && ! str_starts_with($a[0], '✉️') && ! str_starts_with($a[0], '🚨'))->count();

    // [route, icône, libellé, actif, visible, compteur]
    $sections = [
        'Mon espace' => [
            [route('account.space'), 'dashboard', 'Tableau de bord', $is('account.space'), true],
            [route('assist.create'), 'emoji:🚨', 'Je suis en panne', $is('assist.*'), feature('assist_enabled')],
            [route('search'), 'search', 'Trouver un pro', $is('search', 'providers.show') && request('type') !== 'station', true],
            [route('products.index'), 'cube', 'Pièces détachées', $is('products.*'), $market],
            [route('search', ['type' => 'station']), 'emoji:⛽', 'Stations', $is('search') && request('type') === 'station', true],
            [route('electric'), 'emoji:⚡', 'Électrique', $is('electric'), feature('electric_page_enabled')],
        ],
        'Mon suivi' => [
            [route('account.assists'), 'phone', 'Mes dépannages', $is('account.assists'), feature('assist_enabled')],
            [route('part-requests.index'), 'flag', 'Mes demandes de pièces', $is('part-requests.*'), $requestsOn, $offers ?: null],
            [route('account.vehicles'), 'emoji:🚗', 'Mes véhicules', $is('account.vehicles'), true],
            [route('logbook.index'), 'wrench', 'Carnet d\'entretien', $is('logbook.*'), feature('logbook_enabled'), $due ?: null],
            [route('account.favorites'), 'emoji:❤️', 'Mes favoris', $is('account.favorites'), true],
            [route('account.reviews'), 'star', 'Mes avis', $is('account.reviews'), true],
        ],
        'Compte' => [
            [route('account.show'), 'users', 'Profil et sécurité', $is('account.show'), true],
            [route('emergency'), 'emoji:☎️', 'Numéros d\'urgence', $is('emergency'), true],
        ],
    ];
    // Format commun du menu latéral : [url, actif, icône, libellé, compteur]
    $navSections = array_filter(array_map(
        fn ($links) => array_values(array_map(fn ($l) => [$l[0], $l[3], $l[1], $l[2], $l[5] ?? null], array_filter($links, fn ($l) => $l[4]))),
        $sections,
    ));
    $impersonating = session()->has(\App\Http\Controllers\Admin\ImpersonationController::SESSION_KEY);
    $firstName = \Illuminate\Support\Str::of($me->name)->before(' ');
@endphp
<!DOCTYPE html>
<html lang="fr" x-data="adminShell">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (\App\Support\Channels::pushReady()) <meta name="vapid-key" content="{{ config('webpush.vapid.public_key') }}"> @endif
    <meta name="theme-color" content="{{ \App\Support\Theme::brand() }}">
    <title>{{ $title ? $title.' · '.setting('site_name') : 'Mon espace · '.setting('site_name') }}</title>
    @if ($description) <meta name="description" content="{{ $description }}"> @endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeCss) <style>{!! $themeCss !!}</style> @endif
</head>
<body class="app-shell min-h-dvh bg-ink-50 pb-20 lg:pb-0">
    @if ($impersonating)
        <div class="sticky top-0 z-[60] flex flex-wrap items-center justify-center gap-3 bg-amber-400 px-4 py-2 text-sm font-medium text-amber-950">
            <span>👁 Vous êtes dans le compte de <strong>{{ $me->name }}</strong> (accès administrateur).</span>
            <form method="POST" action="{{ route('impersonation.stop') }}">@csrf
                <button class="rounded-lg bg-amber-950 px-3 py-1 text-xs font-bold text-white">Revenir à mon compte admin</button>
            </form>
        </div>
    @endif

    <div class="lg:grid lg:transition-[grid-template-columns]" :class="collapsed ? 'lg:grid-cols-[84px_minmax(0,1fr)]' : 'lg:grid-cols-[272px_minmax(0,1fr)]'">
        {{-- Menu latéral (tiroir sur téléphone) --}}
        <div x-cloak x-show="menu" @click="menu = false" class="fixed inset-0 z-40 bg-black/40 lg:hidden"></div>
        <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-ink-100 bg-white transition lg:sticky lg:top-0 lg:h-dvh lg:w-auto lg:translate-x-0">
            <a href="{{ route('account.space') }}" class="flex h-[72px] shrink-0 items-center gap-3 border-b border-ink-100 px-6" :class="collapsed && 'lg:justify-center lg:px-0'">
                <span class="on-color flex size-11 shrink-0 items-center justify-center rounded-2xl bg-ink-800 shadow-sm"><x-logo class="size-7" /></span>
                <span class="text-xl font-extrabold tracking-tight" :class="collapsed && 'lg:hidden'">Ma<span class="text-brand-500">Panne</span></span>
            </a>

            <div class="flex items-center gap-3 border-b border-ink-100 px-5 py-4" :class="collapsed && 'lg:hidden'">
                <span class="on-color flex size-10 shrink-0 items-center justify-center rounded-full bg-ink-800 text-sm font-bold text-white">{{ $me->initials() }}</span>
                <span class="min-w-0">
                    <span class="block truncate font-semibold">{{ $me->name }}</span>
                    <span class="block truncate text-xs text-ink-500">{{ $me->city?->name ?? 'Client' }}</span>
                </span>
            </div>

            <x-side-nav :sections="$navSections" :icons="['Mon suivi' => 'history', 'Compte' => 'users']">
                <form method="POST" action="{{ route('logout') }}" class="mt-3 border-t border-ink-100 pt-3">@csrf
                    <button class="flex w-full items-center gap-3.5 rounded-xl px-3.5 py-2.5 text-[15px] text-red-600 hover:bg-red-50" :class="collapsed && 'lg:justify-center lg:px-0'">
                        <x-icon name="logout" class="size-5" /><span :class="collapsed && 'lg:hidden'">Se déconnecter</span>
                    </button>
                </form>
            </x-side-nav>

            <button @click="toggleCollapse()" class="hidden h-12 shrink-0 items-center justify-center gap-2 border-t border-ink-100 text-sm text-ink-400 hover:text-ink-700 lg:flex">
                <x-icon name="collapse" class="size-4" /><span x-text="collapsed ? '' : 'Réduire'">Réduire</span>
            </button>
        </aside>

        <div class="min-w-0">
            {{-- Barre supérieure : menu, recherche, alertes, compte --}}
            <header class="sticky top-0 z-30 flex h-[72px] items-center gap-3 border-b border-ink-100 bg-white/95 px-4 backdrop-blur lg:px-8">
                <button @click="window.innerWidth < 1024 ? menu = true : toggleCollapse()" class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-ink-100 text-ink-600 hover:bg-ink-50" aria-label="Menu">
                    <x-icon name="sidebar" />
                </button>
                <form action="{{ route('search') }}" class="relative min-w-0 flex-1 sm:max-w-xl">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-400" />
                    <input name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" class="h-11 w-full rounded-xl border border-ink-100 bg-ink-50 pr-3 pl-11 text-[15px] placeholder:text-ink-400 focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100 focus:outline-none"
                           placeholder="Ex : ma moto ne démarre pas" aria-label="Rechercher un pro">
                </form>

                <div class="ml-auto flex items-center gap-2">
                    @if (feature('assist_enabled'))
                        <a href="{{ route('assist.create') }}" class="btn hidden bg-red-600 text-white hover:bg-red-700 md:inline-flex">🚨 Je suis en panne</a>
                    @endif
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.outside="open = false" class="relative flex size-11 items-center justify-center rounded-xl border border-ink-100 text-ink-600 hover:bg-ink-50" aria-label="Notifications">
                            <x-icon name="bell" />
                            @if (count($alerts))
                                <span class="on-color absolute -top-1 -right-1 min-w-5 rounded-full bg-brand-500 px-1 text-center text-[11px] leading-5 font-bold text-white">{{ count($alerts) }}</span>
                            @endif
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl bg-white text-sm shadow-xl ring-1 ring-ink-100">
                            <p class="border-b border-ink-50 px-4 py-3 font-semibold">À suivre</p>
                            @forelse ($alerts as [$label, $detail, $url])
                                <a href="{{ $url }}" class="block px-4 py-2.5 hover:bg-ink-50"><span class="font-medium">{{ $label }}</span>@if ($detail)<span class="block truncate text-xs text-ink-500">{{ $detail }}</span>@endif</a>
                            @empty
                                <p class="px-4 py-4 text-ink-400">Rien à signaler. 👌</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="relative hidden sm:block" x-data="{ open: false }">
                        <button @click="open = !open" @click.outside="open = false" class="flex h-11 items-center gap-2 rounded-xl border border-ink-100 py-1 pr-3 pl-1 hover:bg-ink-50">
                            <span class="on-color flex size-9 items-center justify-center rounded-full bg-ink-800 text-xs font-bold text-white">{{ $me->initials() }}</span>
                            <span class="hidden max-w-32 truncate text-[15px] font-medium md:block">{{ $firstName }}</span>
                            <x-icon name="chevron" class="size-4 text-ink-400" />
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl bg-white py-1 text-sm shadow-xl ring-1 ring-ink-100">
                            <a href="{{ route('account.show') }}" class="flex items-center gap-2 px-4 py-2.5 hover:bg-ink-50"><x-icon name="users" class="size-4" />Profil et sécurité</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="flex w-full items-center gap-2 px-4 py-2.5 text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" />Se déconnecter</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            @if (! $me->hasVerifiedEmail() && ! request()->routeIs('verification.*'))
                <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-xs text-amber-800">
                    Confirmez votre email {{ $me->email }}.
                    <form method="POST" action="{{ route('verification.send') }}" class="inline">@csrf<button class="font-semibold underline">Renvoyer le lien</button></form>
                </div>
            @endif
            @if (feature('announcement_enabled') && filled(setting('announcement_text')))
                <div class="bg-ink-800 px-4 py-2 text-center text-sm text-white">{{ setting('announcement_text') }}</div>
            @endif

            <main @class(['mx-auto px-4 py-6 lg:px-8 lg:py-8', 'max-w-6xl' => ! $wide, 'max-w-7xl' => $wide])>
                @if (session('success'))
                    <div class="on-color mb-4 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white" role="status">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="on-color mb-4 rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white" role="alert">{{ session('error') }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Barre du bas (téléphone) : les actions clés à portée de pouce --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-100 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden">
        <div class="grid grid-cols-5 items-end text-[11px] font-medium text-ink-500">
            <a href="{{ route('account.space') }}" @class(['flex flex-col items-center gap-0.5 py-2', 'text-brand-600' => $is('account.space')])>
                <x-icon name="home" class="size-6" />Accueil
            </a>
            <a href="{{ route('search') }}" @class(['flex flex-col items-center gap-0.5 py-2', 'text-brand-600' => $is('search', 'providers.show')])>
                <x-icon name="search" class="size-6" />Pros
            </a>
            @if (feature('assist_enabled'))
                <a href="{{ route('assist.create') }}" class="-mt-4 flex flex-col items-center gap-0.5 pb-2 text-red-600">
                    <span class="on-color flex size-14 items-center justify-center rounded-full bg-red-600 text-2xl text-white shadow-lg ring-4 ring-white">🚨</span>Panne
                </a>
            @else
                <a href="{{ route('emergency') }}" class="flex flex-col items-center gap-0.5 py-2 text-red-600"><span class="text-xl">☎️</span>Urgence</a>
            @endif
            <a href="{{ $market ? route('products.index') : route('search', ['type' => 'station']) }}" @class(['flex flex-col items-center gap-0.5 py-2', 'text-brand-600' => $is('products.*')])>
                <x-icon name="cube" class="size-6" />{{ $market ? 'Pièces' : 'Stations' }}
            </a>
            <button type="button" @click="menu = true" class="relative flex flex-col items-center gap-0.5 py-2">
                <x-icon name="sidebar" class="size-6" />Menu
                @if (count($alerts)) <span class="absolute top-1 right-1/4 size-2.5 rounded-full bg-brand-500"></span> @endif
            </button>
        </div>
    </nav>
</body>
</html>
