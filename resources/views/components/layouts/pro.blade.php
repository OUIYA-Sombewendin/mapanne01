@props(['title' => null])
@php
    $me = auth()->user();
    $provider = $me->provider;
    $myRole = $me->shopRole($provider);
    $shops = $me->shops();
    $sells = $provider->canSellParts();
    $managing = feature('shop_management_enabled');
    // Demandes de dépannage en attente pour cette boutique (non mis en cache : c'est urgent).
    $assistOpen = feature('assist_enabled') && $me->canShop('assist')
        ? \App\Models\AssistRequest::where('status', 'open')->whereHas('notifiedProviders', fn ($q) => $q->whereKey($provider->id)->whereNull('assist_dispatches.declined_at'))->count()
        : null;

    // Points d'attention de la boutique active (mis en cache 60 s).
    $alerts = cache()->remember("pro.alerts.{$provider->id}.{$myRole}", 60, function () use ($me, $provider, $sells) {
        $items = [];
        if ($provider->status === \App\Enums\ProviderStatus::Pending && $me->canShop('profile')) {
            $items[] = ['Fiche en attente de validation', null, route('pro.profile')];
        }
        if ($provider->status === \App\Enums\ProviderStatus::Rejected && $me->canShop('profile')) {
            $items[] = ['Fiche refusée : à corriger', null, route('pro.dashboard')];
        }
        if ($sells && $me->canShop('stock') && feature('shop_management_enabled')) {
            $low = $provider->products()->where('on_order', false)->whereRaw('stock <= COALESCE(stock_alert, 0)')->count();
            if ($low) {
                $items[] = ['Pièces à réapprovisionner', $low, route('pro.manage.stock', ['filter' => 'low'])];
            }
        }
        if ($me->canShop('invoices') && feature('shop_management_enabled')) {
            $unpaid = $provider->invoices()->where('kind', 'invoice')->whereIn('status', ['unpaid', 'partial'])->count();
            if ($unpaid) {
                $items[] = ['Factures non soldées', $unpaid, route('pro.manage.invoices.index', ['status' => 'unpaid'])];
            }
        }
        if ($sells && $me->canShop('requests') && feature('marketplace_enabled') && feature('part_requests_enabled') && $provider->isVisible()) {
            $open = \App\Models\PartRequest::open()->where('city_id', $provider->city_id)->whereDoesntHave('offers', fn ($q) => $q->where('provider_id', $provider->id))->count();
            if ($open) {
                $items[] = ['Demandes de pièces sans offre', $open, route('pro.part-requests.index')];
            }
        }

        return $items;
    });
    $alertCount = array_sum(array_map(fn ($a) => $a[1] ?? 1, $alerts));
    // Messages de l'équipe (validation de la fiche, demandes de modification) non lus.
    $unread = $me->unreadNotifications()->latest()->take(5)->get();
    $unreadCount = $unread->count() < 5 ? $unread->count() : $me->unreadNotifications()->count();
    $bellCount = $alertCount + $unreadCount;

    $is = fn (...$patterns) => request()->routeIs(...$patterns);
    $sections = [
        'Ma boutique' => [
            ['pro.dashboard', 'dashboard', 'Tableau de bord', $is('pro.dashboard'), true],
            ['pro.assist.index', 'phone', 'Dépannages', $is('pro.assist.*'), $assistOpen !== null, $assistOpen ?: null],
            ['pro.profile', 'document', 'Fiche', $is('pro.profile'), $me->canShop('profile')],
            ['pro.schedule', 'clock', 'Horaires', $is('pro.schedule'), $me->canShop('profile')],
            ['pro.verification', 'shield', 'Badges de confiance', $is('pro.verification*'), $me->canShop('profile')],
            ['pro.stats', 'chart', 'Statistiques', $is('pro.stats'), $me->canShop('stats') && $provider->hasPlanFeature('statistics')],
        ],
        'Ventes' => [
            ['pro.products.index', 'cube', 'Catalogue', $is('pro.products.*'), $sells && $me->canShop('catalog') && $provider->hasPlanFeature('catalog')],
            ['pro.manage.invoices.index', 'card', 'Ventes et factures', $is('pro.manage.invoices.*'), $managing && $me->canShop('invoices')],
            ['pro.part-requests.index', 'flag', 'Demandes clients', $is('pro.part-requests.*'), $sells && $me->canShop('requests') && feature('marketplace_enabled') && feature('part_requests_enabled') && $provider->isVisible() && $provider->hasPlanFeature('part_requests')],
        ],
        'Gestion' => [
            ['pro.manage.index', 'chart', 'Vue d\'ensemble', $is('pro.manage.index'), $managing && $me->canShop('reports')],
            ['pro.manage.stock', 'layers', 'Stock', $is('pro.manage.stock*'), $managing && $sells && $me->canShop('stock')],
            ['pro.manage.counts', 'document', 'Inventaire physique', $is('pro.manage.counts*'), $managing && $sells && $me->canShop('stock')],
            ['pro.manage.expenses.index', 'transfer', 'Dépenses', $is('pro.manage.expenses.*'), $managing && $me->canShop('expenses')],
            ['pro.manage.reports', 'history', 'Rapports', $is('pro.manage.reports*'), $managing && $me->canShop('reports')],
        ],
        'Compte' => [
            ['pro.shops.index', 'shop', 'Mes boutiques', $is('pro.shops.*'), $shops->count() > 1 || ($sells && $myRole === 'owner' && feature('multi_shop_enabled'))],
            ['pro.team.index', 'users', 'Équipe', $is('pro.team.*'), $me->canShop('team') && feature('shop_teams_enabled')],
            ['pro.subscription', 'layers', 'Abonnement', $is('pro.subscription*'), $me->canShop('subscription') && feature('subscriptions_enabled')],
        ],
    ];
    $sections = array_filter(array_map(fn ($links) => array_values(array_filter($links, fn ($l) => $l[4])), $sections));
    // Format commun du menu latéral : [url, actif, icône, libellé, compteur]
    $navSections = array_map(fn ($links) => array_map(fn ($l) => [route($l[0]), $l[3], $l[1], $l[2], $l[5] ?? null], $links), $sections);
    $impersonating = session()->has(\App\Http\Controllers\Admin\ImpersonationController::SESSION_KEY);
@endphp
<!DOCTYPE html>
<html lang="fr" x-data="adminShell" :class="{ 'admin-dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (\App\Support\Channels::pushReady()) <meta name="vapid-key" content="{{ config('webpush.vapid.public_key') }}"> @endif
    <meta name="robots" content="noindex">
    <title>{{ $title ? $title.' · ' : '' }}{{ $provider->business_name }} · {{ setting('site_name') }} Pro</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>try { if (localStorage.getItem('mp-admin-dark') === '1') document.documentElement.classList.add('admin-dark') } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeCss) <style>{!! $themeCss !!}</style> @endif
</head>
<body class="app-shell min-h-dvh bg-ink-50">
    @if ($impersonating)
        <div class="sticky top-0 z-[60] flex flex-wrap items-center justify-center gap-3 bg-amber-400 px-4 py-2 text-sm font-medium text-amber-950">
            <span>👁 Vous êtes dans le compte de <strong>{{ $me->name }}</strong> (accès administrateur).</span>
            <form method="POST" action="{{ route('impersonation.stop') }}">@csrf
                <button class="rounded-lg bg-amber-950 px-3 py-1 text-xs font-bold text-white">Revenir à mon compte admin</button>
            </form>
        </div>
    @endif

    <div class="lg:grid lg:transition-[grid-template-columns]" :class="collapsed ? 'lg:grid-cols-[84px_minmax(0,1fr)]' : 'lg:grid-cols-[288px_minmax(0,1fr)]'">
        {{-- Menu latéral --}}
        <div x-cloak x-show="menu" @click="menu = false" class="fixed inset-0 z-40 bg-black/40 lg:hidden"></div>
        <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-ink-100 bg-white transition lg:sticky lg:top-0 lg:h-dvh lg:w-auto lg:translate-x-0">
            <a href="{{ route('home') }}" class="flex h-[84px] shrink-0 items-center gap-3 border-b border-ink-100 px-6" :class="collapsed && 'lg:justify-center lg:px-0'">
                <span class="on-color flex size-12 shrink-0 items-center justify-center rounded-2xl bg-ink-800 shadow-sm"><x-logo class="size-8" /></span>
                <span class="text-xl font-extrabold tracking-tight" :class="collapsed && 'lg:hidden'">{{ \Illuminate\Support\Str::of(setting('site_name'))->substr(0, 2) }}<span class="text-brand-500">{{ \Illuminate\Support\Str::of(setting('site_name'))->substr(2) }}</span> <span class="text-sm font-semibold text-ink-400">Pro</span></span>
            </a>

            {{-- Boutique active --}}
            <div class="border-b border-ink-100 p-4" :class="collapsed && 'lg:hidden'" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false" class="flex w-full items-center gap-3 rounded-xl bg-ink-50 px-3 py-2.5 text-left hover:bg-ink-100" @if ($shops->count() < 2) disabled @endif>
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-lg ring-1 ring-ink-100">{{ $provider->type->icon() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{ $provider->business_name }}</span>
                        <span class="block truncate text-xs text-ink-500">{{ \App\Support\ShopTeam::label($myRole) }} · {{ $provider->status->label() }}</span>
                    </span>
                    @if ($shops->count() > 1) <x-icon name="chevron" class="size-4 text-ink-400" /> @endif
                </button>
                @if ($shops->count() > 1)
                    <div x-cloak x-show="open" x-transition.origin.top class="mt-2 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-xl ring-1 ring-ink-100">
                        @foreach ($shops as $shop)
                            <form method="POST" action="{{ route('pro.shops.switch', $shop) }}">@csrf
                                <button @class(['flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-ink-50', 'font-semibold' => $shop->id === $provider->id])>
                                    <span>{{ $shop->type->icon() }}</span><span class="flex-1 truncate">{{ $shop->business_name }}</span>
                                    @if ($shop->id === $provider->id) <span class="text-brand-600">✓</span> @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>

            <x-side-nav :sections="$navSections" :icons="['Ventes' => 'card', 'Gestion' => 'chart', 'Compte' => 'users']">
                <div class="mt-3 border-t border-ink-100 pt-3">
                    <a href="{{ route('providers.show', $provider) }}" target="_blank" class="flex items-center gap-3.5 rounded-xl px-3.5 py-2.5 text-[15px] text-ink-700 hover:bg-accent-50 hover:text-accent-700" :class="collapsed && 'lg:justify-center lg:px-0'">
                        <x-icon name="external" class="size-5 text-ink-500" /><span :class="collapsed && 'lg:hidden'">Voir ma page publique</span>
                    </a>
                </div>
            </x-side-nav>

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
                @if ($sells && $me->canShop('catalog'))
                    <form action="{{ route('pro.products.index') }}" class="relative hidden max-w-xl flex-1 sm:block">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-400" />
                        <input name="q" value="{{ request()->routeIs('pro.products.index') ? request('q') : '' }}" class="h-11 w-full rounded-xl border border-ink-100 bg-ink-50 pr-4 pl-12 text-[15px] placeholder:text-ink-400 focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100 focus:outline-none" placeholder="Rechercher une pièce dans mon catalogue…">
                    </form>
                @else
                    <p class="hidden truncate font-semibold sm:block">{{ $provider->business_name }}</p>
                @endif

                <div class="ml-auto flex items-center gap-2 sm:gap-3">
                    @if ($managing && $me->canShop('invoices'))
                        <a href="{{ route('pro.manage.invoices.create') }}" class="btn btn-primary hidden md:inline-flex"><x-icon name="plus" /> Nouvelle vente</a>
                    @endif
                    <button @click="toggleDark()" class="flex size-11 items-center justify-center rounded-xl border border-ink-100 text-ink-600 hover:bg-ink-50" :aria-label="dark ? 'Mode clair' : 'Mode sombre'">
                        <x-icon name="moon" x-show="!dark" /><x-icon name="sun" x-show="dark" x-cloak />
                    </button>

                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.outside="open = false" class="relative flex size-11 items-center justify-center rounded-xl border border-ink-100 text-ink-600 hover:bg-ink-50" aria-label="Notifications">
                            <x-icon name="bell" />
                            @if ($bellCount)
                                <span class="on-color absolute -top-1 -right-1 min-w-5 rounded-full bg-brand-500 px-1 text-center text-[11px] leading-5 font-bold text-white">{{ $bellCount > 99 ? '99+' : $bellCount }}</span>
                            @endif
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl bg-white text-sm shadow-xl ring-1 ring-ink-100">
                            @if ($unread->isNotEmpty())
                                <div class="flex items-center justify-between border-b border-ink-50 px-4 py-3">
                                    <p class="font-semibold">Messages de l’équipe</p>
                                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-xs text-accent-700 hover:underline">Tout marquer comme lu</button></form>
                                </div>
                                @foreach ($unread as $n)
                                    <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-2 bg-accent-50/50 px-4 py-2.5 hover:bg-ink-50">
                                        <span>{{ $n->data['icon'] ?? '🔔' }}</span>
                                        <span class="min-w-0"><span class="block font-semibold">{{ $n->data['title'] ?? 'Notification' }}</span><span class="block text-xs text-ink-500">{{ $n->data['body'] ?? '' }}</span><span class="block text-[11px] text-ink-400">{{ $n->created_at->diffForHumans() }}</span></span>
                                    </a>
                                @endforeach
                            @endif
                            <p class="border-b border-ink-50 px-4 py-3 font-semibold">À traiter</p>
                            @forelse ($alerts as [$label, $count, $url])
                                <a href="{{ $url }}" class="flex items-center justify-between px-4 py-2.5 hover:bg-ink-50"><span>{{ $label }}</span>@if ($count)<span class="on-color rounded-full bg-brand-500 px-2 text-xs font-bold text-white">{{ $count }}</span>@endif</a>
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
                                <p class="text-xs text-ink-400">{{ \App\Support\ShopTeam::label($myRole) }} · {{ $provider->business_name }}</p>
                            </div>
                            <a href="{{ route('account.show') }}" class="flex items-center gap-2 px-4 py-2.5 hover:bg-ink-50"><x-icon name="users" class="size-4" />Mon compte</a>
                            <a href="{{ route('home') }}" class="flex items-center gap-2 px-4 py-2.5 hover:bg-ink-50"><x-icon name="home" class="size-4" />Site {{ setting('site_name') }}</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="flex w-full items-center gap-2 px-4 py-2.5 text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" />Se déconnecter</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 lg:px-8 lg:py-8">
                {{-- Statut de la fiche, visible sur toutes les pages (le tableau de bord donne le détail) --}}
                @unless (request()->routeIs('pro.dashboard'))
                    @switch($provider->status)
                        @case(\App\Enums\ProviderStatus::Pending)
                            <a href="{{ route('pro.dashboard') }}" class="mb-4 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                <span class="text-lg">⏳</span><span class="flex-1"><strong>En attente de validation</strong> : votre fiche n’apparaît pas encore aux clients. L’équipe vous prévient dès qu’elle est validée.</span><span class="font-semibold">Voir →</span>
                            </a>
                            @break
                        @case(\App\Enums\ProviderStatus::Rejected)
                            <a href="{{ route('pro.dashboard') }}" class="mb-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                                <span class="text-lg">✋</span><span class="flex-1"><strong>Fiche refusée</strong> : {{ \Illuminate\Support\Str::limit($provider->rejection_reason, 120) }}</span><span class="font-semibold">Corriger →</span>
                            </a>
                            @break
                        @case(\App\Enums\ProviderStatus::Suspended)
                            <div class="mb-4 flex items-center gap-3 rounded-xl border border-ink-200 bg-ink-100 px-4 py-3 text-sm">
                                <span class="text-lg">⛔</span><span class="flex-1"><strong>Fiche bloquée</strong> : elle n’apparaît plus aux clients. {{ $provider->rejection_reason }} · Support : {{ setting('support_email') }}</span>
                            </div>
                            @break
                    @endswitch
                @endunless
                @if ($provider->requestedChanges() && $me->canShop('profile'))
                    <a href="{{ route('pro.profile') }}" class="mb-4 flex items-center gap-3 rounded-xl border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-900">
                        <span class="text-lg">📝</span><span class="flex-1">Votre demande de modification de la fiche est en cours de vérification par l’équipe.</span>
                    </a>
                @endif
                @if (session('success'))
                    <div class="on-color mb-4 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="on-color mb-4 rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white">{{ session('error') }}</div>
                @endif
                @if ($errors->any() && ! session('importErrors'))
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
