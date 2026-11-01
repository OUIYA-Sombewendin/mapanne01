@php
    $f = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' F';
@endphp
<x-layouts.admin title="Observatoire des prix">
    <h1 class="mb-1 text-2xl font-extrabold">Observatoire des prix</h1>
    <p class="mb-5 text-sm text-ink-500">Prix des pièces publiées par les boutiques (pièces visibles uniquement). Le médian est le prix du milieu : la moitié des boutiques vend moins cher, l'autre moitié plus cher.</p>

    <x-filter-bar :keys="['q', 'category', 'city', 'vehicle', 'condition']">
        <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-60" placeholder="Pièce ou référence (ex. plaquette)">
        <select name="category" class="input-sm">
            <option value="">Toutes les catégories</option>
            @foreach ($partCategories as $c)
                <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->icon }} {{ $c->name }}</option>
                @foreach ($c->children as $sub) <option value="{{ $sub->id }}" @selected(request('category') == $sub->id)>&nbsp;&nbsp;› {{ $sub->name }}</option> @endforeach
            @endforeach
        </select>
        <select name="city" class="input-sm">
            <option value="">Toutes les villes</option>
            @foreach ($cities as $c) <option value="{{ $c->id }}" @selected(request('city') == $c->id)>{{ $c->name }}</option> @endforeach
        </select>
        <select name="vehicle" class="input-sm">
            <option value="">Tous les véhicules</option>
            @foreach ($vehicles as $k => $l) <option value="{{ $k }}" @selected(request('vehicle') === $k)>{{ $l }}</option> @endforeach
        </select>
        <select name="condition" class="input-sm">
            <option value="">Neuf et occasion</option>
            @foreach ($conditions as $k => $l) <option value="{{ $k }}" @selected(request('condition') === $k)>{{ $l }}</option> @endforeach
        </select>
    </x-filter-bar>

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([['cube', $summary->products, 'Pièces'], ['shop', $summary->shops, 'Boutiques'], ['chart', $f($summary->min), 'Prix minimum'], ['layers', $f($summary->median), 'Prix médian'], ['card', $f($summary->avg), 'Prix moyen'], ['flag', $f($summary->max), 'Prix maximum']] as [$icon, $value, $label])
            <div class="card border-t-4 border-accent-600 p-4 text-center">
                <span class="mx-auto flex size-11 items-center justify-center rounded-xl bg-accent-50 text-accent-700"><x-icon :name="$icon" class="size-6" /></span>
                <p class="mt-2 text-2xl font-extrabold">{{ $value }}</p>
                <p class="text-sm font-semibold">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 2xl:grid-cols-2">
        <section class="card overflow-x-auto p-5">
            <h2 class="mb-3 text-lg font-extrabold" style="color: var(--heading)">Par catégorie</h2>
            <table class="data-table">
                <thead><tr><th>Catégorie</th><th class="text-right">Pièces</th><th class="text-right">Boutiques</th><th class="text-right">Min</th><th class="text-right">Médian</th><th class="text-right">Max</th></tr></thead>
                <tbody>
                    @forelse ($categories as $c)
                        <tr>
                            <td><a href="{{ route('admin.price-watch', ['category' => $c->id] + request()->except(['category', 'page'])) }}" class="font-semibold hover:text-accent-700">{{ $c->icon }} {{ $c->name }}</a></td>
                            <td class="text-right">{{ $c->products }}</td>
                            <td class="text-right">{{ $c->shops }}</td>
                            <td class="text-right whitespace-nowrap">{{ $f($c->min) }}</td>
                            <td class="text-right font-semibold whitespace-nowrap">{{ $f($c->median) }}</td>
                            <td class="text-right whitespace-nowrap">{{ $f($c->max) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-ink-400">Aucune pièce pour ces filtres.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card overflow-x-auto p-5">
            <h2 class="mb-1 text-lg font-extrabold" style="color: var(--heading)">Par pièce</h2>
            <p class="mb-3 text-xs text-ink-500">Pièces au nom identique, les plus proposées d'abord. Un grand écart entre min et max signale souvent des qualités différentes ou une erreur de saisie.</p>
            <table class="data-table">
                <thead><tr><th>Pièce</th><th class="text-right">Boutiques</th><th class="text-right">Min</th><th class="text-right">Médian</th><th class="text-right">Max</th></tr></thead>
                <tbody>
                    @forelse ($parts as $p)
                        <tr>
                            <td>{{ $p->label }}</td>
                            <td class="text-right">{{ $p->shops }}</td>
                            <td class="text-right whitespace-nowrap">{{ $f($p->min) }}</td>
                            <td class="text-right font-semibold whitespace-nowrap">{{ $f($p->median) }}</td>
                            <td @class(['text-right whitespace-nowrap', 'font-semibold text-red-700' => $p->min > 0 && $p->max > 3 * $p->min])>{{ $f($p->max) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-ink-400">Aucune pièce pour ces filtres.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-layouts.admin>
