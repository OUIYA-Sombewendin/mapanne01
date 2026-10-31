<x-layouts.app title="Mes boutiques">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-extrabold">Mes boutiques</h1>
        @if ($canAdd)
            <a href="{{ route('pro.shops.create') }}" class="btn btn-primary btn-sm">+ Déclarer une boutique</a>
        @endif
    </div>
    <p class="mb-4 text-sm text-ink-500">Chaque boutique a sa propre fiche, son stock, ses ventes et son équipe. Choisissez celle sur laquelle vous travaillez.
        @if ($owned) Vous possédez {{ $owned }} boutique(s) sur {{ $max }} possibles. @endif</p>
    @include('pro._nav')

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($shops as $shop)
            @php($active = $current?->id === $shop->id)
            <article @class(['card flex flex-col p-5', 'ring-2 ring-brand-500' => $active])>
                <div class="flex items-start gap-3">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-2xl">{{ $shop->type->icon() }}</span>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate font-bold">{{ $shop->business_name }}</h2>
                        <p class="text-sm text-ink-500">{{ $shop->type->label() }} · {{ $shop->neighborhood?->name ?? $shop->city?->name }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span class="chip {{ $shop->status->color() }}">{{ $shop->status->label() }}</span>
                    <span class="chip bg-ink-100 text-ink-700">{{ \App\Support\ShopTeam::label($shop->my_role) }}</span>
                    @if ($active) <span class="chip bg-brand-100 text-brand-800">Boutique active</span> @endif
                </div>
                <div class="mt-4 flex gap-2 border-t border-ink-50 pt-3">
                    @unless ($active)
                        <form method="POST" action="{{ route('pro.shops.switch', $shop) }}">@csrf<button class="btn btn-dark btn-sm">Travailler sur cette boutique</button></form>
                    @endunless
                    <a href="{{ route('providers.show', $shop) }}" class="btn btn-ghost btn-sm">Voir la page ↗</a>
                </div>
            </article>
        @endforeach
    </div>

    @if (! $canAdd && $owned && feature('multi_shop_enabled'))
        <p class="mt-4 text-sm text-ink-500">Vous ne pouvez pas déclarer de nouvelle boutique : la limite est atteinte, ou votre activité ne vend pas de pièces détachées.</p>
    @endif
</x-layouts.app>
