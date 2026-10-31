@php
    $me = auth()->user();
    $provider = $me->provider;
    $myRole = $me->shopRole($provider);
    $shops = $me->shops();
    $sells = $provider->canSellParts();
    $links = [['pro.dashboard', 'Tableau de bord', 'pro.dashboard']];
    if ($me->canShop('profile')) {
        $links[] = ['pro.profile', 'Fiche', 'pro.profile'];
        $links[] = ['pro.schedule', 'Horaires', 'pro.schedule'];
    }
    if ($sells && $me->canShop('catalog') && $provider->hasPlanFeature('catalog')) {
        $links[] = ['pro.products.index', 'Catalogue', 'pro.products.*'];
    }
    if (feature('shop_management_enabled')) {
        // « Gestion » mène à la première zone permise par le rôle.
        $manage = collect([['reports', 'pro.manage.index'], ['stock', 'pro.manage.stock'], ['invoices', 'pro.manage.invoices.index'], ['expenses', 'pro.manage.expenses.index']])
            ->first(fn ($z) => $me->canShop($z[0]) && ($z[0] !== 'stock' || $sells));
        if ($manage) {
            $links[] = [$manage[1], 'Gestion', 'pro.manage.*'];
        }
    }
    if ($sells && $me->canShop('requests') && feature('marketplace_enabled') && feature('part_requests_enabled') && $provider->isVisible() && $provider->hasPlanFeature('part_requests')) {
        $links[] = ['pro.part-requests.index', 'Demandes clients', 'pro.part-requests.*'];
    }
    if ($me->canShop('team') && feature('shop_teams_enabled')) {
        $links[] = ['pro.team.index', 'Équipe', 'pro.team.*'];
    }
    if ($me->canShop('subscription') && feature('subscriptions_enabled')) {
        $links[] = ['pro.subscription', 'Abonnement', 'pro.subscription*'];
    }
@endphp

{{-- Boutique active : sélecteur pour les commerçants multi-boutiques et les employés --}}
@if ($shops->count() > 1 || ($sells && $myRole === 'owner' && feature('multi_shop_enabled')))
    <div class="mb-3 flex flex-wrap items-center gap-2" x-data="{ open: false }">
        <div class="relative">
            <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-ink-100 hover:ring-ink-300">
                <span class="text-lg">{{ $provider->type->icon() }}</span>
                <span class="text-left"><span class="block text-[11px] text-ink-400 uppercase">Boutique active</span><span class="font-semibold">{{ $provider->business_name }}</span></span>
                <x-icon name="chevron" class="size-4 text-ink-400" />
            </button>
            <div x-cloak x-show="open" x-transition.origin.top.left class="absolute left-0 z-30 mt-2 w-72 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-xl ring-1 ring-ink-100">
                @foreach ($shops as $shop)
                    <form method="POST" action="{{ route('pro.shops.switch', $shop) }}">@csrf
                        <button @class(['flex w-full items-center gap-2 px-4 py-2.5 text-left hover:bg-ink-50', 'font-semibold' => $shop->id === $provider->id])>
                            <span>{{ $shop->type->icon() }}</span>
                            <span class="flex-1">{{ $shop->business_name }}<span class="block text-xs font-normal text-ink-400">{{ \App\Support\ShopTeam::label($me->shopRole($shop)) }} · {{ $shop->status->label() }}</span></span>
                            @if ($shop->id === $provider->id) <span class="text-brand-600">✓</span> @endif
                        </button>
                    </form>
                @endforeach
                <a href="{{ route('pro.shops.index') }}" class="block border-t border-ink-50 px-4 py-2.5 text-brand-600 hover:bg-ink-50">Gérer mes boutiques →</a>
            </div>
        </div>
        @if ($myRole !== 'owner') <span class="chip bg-sky-100 text-sky-800">Vous êtes {{ \Illuminate\Support\Str::lower(\App\Support\ShopTeam::label($myRole)) }}</span> @endif
    </div>
@endif

<nav class="-mx-4 mb-5 flex gap-1 overflow-x-auto border-b border-ink-100 px-4 text-sm font-medium">
    @foreach ($links as [$route, $label, $pattern])
        <a href="{{ route($route) }}" @class([
            '-mb-px border-b-2 px-3 py-2.5 whitespace-nowrap',
            'border-brand-500 text-brand-600' => request()->routeIs($pattern),
            'border-transparent text-ink-500 hover:text-ink-800' => ! request()->routeIs($pattern),
        ])>{{ $label }}</a>
    @endforeach
    <a href="{{ route('providers.show', $provider) }}" class="-mb-px border-b-2 border-transparent px-3 py-2.5 whitespace-nowrap text-ink-500 hover:text-ink-800">Voir la page ↗</a>
</nav>
