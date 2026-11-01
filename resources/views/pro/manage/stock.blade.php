@php($money = fn ($v) => \App\Support\ShopReport::money($v))
<x-layouts.pro title="Stock">
    <h1 class="mb-1 text-2xl font-extrabold">Stock</h1>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-500">Tout ce qui est dans votre boutique, publié ou non sur le marketplace. Chaque entrée et sortie est enregistrée.</p>
        <a href="{{ route('pro.manage.counts') }}" class="btn btn-dark btn-sm">📋 Faire l'inventaire</a>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-ink-500">Pièces en stock</p><p class="text-2xl font-extrabold">{{ number_format($value['units'], 0, ',', ' ') }}</p></div>
        <div class="card p-4"><p class="text-xs text-ink-500">Valeur d'achat</p><p class="text-2xl font-extrabold">{{ $money($value['cost']) }}</p></div>
        <div class="card p-4"><p class="text-xs text-ink-500">Valeur de vente</p><p class="text-2xl font-extrabold">{{ $money($value['retail']) }}</p></div>
        <a href="{{ route('pro.manage.stock', ['filter' => 'low']) }}" class="card p-4 hover:ring-amber-300"><p class="text-xs text-ink-500">Alertes de stock</p><p @class(['text-2xl font-extrabold', 'text-amber-600' => $lowCount])>{{ $lowCount }}</p></a>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div>
            <x-filter-bar :keys="['q', 'filter', 'category', 'shelf']">
                <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-48" placeholder="Nom, référence, code-barres">
                <select name="filter" class="input-sm">
                    <option value="">Toutes les pièces</option>
                    <option value="low" @selected(request('filter') === 'low')>Sous le seuil d'alerte</option>
                    <option value="out" @selected(request('filter') === 'out')>Stock à zéro</option>
                    <option value="nocost" @selected(request('filter') === 'nocost')>Sans prix d'achat</option>
                    <option value="unpublished" @selected(request('filter') === 'unpublished')>Non publiées</option>
                </select>
                <select name="category" class="input-sm">
                    <option value="">Toutes catégories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
                        @foreach ($c->children as $sub) <option value="{{ $sub->id }}" @selected(request('category') == $sub->id)>&nbsp;&nbsp;› {{ $sub->name }}</option> @endforeach
                    @endforeach
                </select>
                @if ($shelves->isNotEmpty())
                    <select name="shelf" class="input-sm">
                        <option value="">Tous les rayons</option>
                        @foreach ($shelves as $s) <option @selected(request('shelf') === $s)>{{ $s }}</option> @endforeach
                    </select>
                @endif
                <a href="{{ route('pro.manage.stock.movements') }}" class="ml-auto text-sm font-medium text-brand-600">Historique des mouvements →</a>
            </x-filter-bar>

            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><x-sort-th column="name" label="Pièce" /><x-sort-th column="shelf" label="Empl." /><x-sort-th column="stock" label="Stock" align="right" /><x-sort-th column="cost" label="Achat" align="right" /><x-sort-th column="price" label="Vente" align="right" /><x-sort-th column="margin" label="Marge" align="right" /><th></th></tr></thead>
                    <tbody>
                        @forelse ($products as $p)
                            <tr>
                                <td>
                                    <span class="font-semibold">{{ $p->name }}</span>
                                    <span class="block text-xs text-ink-400">{{ $p->category?->name }}{{ $p->reference ? ' · '.$p->reference : '' }}
                                        @unless ($p->is_active) · <span class="text-ink-500">non publié</span> @endunless</span>
                                </td>
                                <td class="text-ink-500">{{ $p->shelf ?: '—' }}</td>
                                <td class="text-right"><span @class(['chip font-bold', 'bg-red-100 text-red-700' => $p->stock === 0 && ! $p->on_order, 'bg-amber-100 text-amber-800' => $p->stock > 0 && $p->isLowStock(), 'bg-ink-50 text-ink-800' => ! $p->isLowStock()])>{{ $p->stock }}</span></td>
                                <td class="text-right whitespace-nowrap">{{ $p->cost_price !== null ? number_format($p->cost_price, 0, ',', ' ') : '—' }}</td>
                                <td class="text-right whitespace-nowrap">{{ number_format($p->price, 0, ',', ' ') }}</td>
                                <td class="text-right whitespace-nowrap">@if ($p->unitMargin() !== null)<span @class(['text-red-600' => $p->unitMargin() < 0])>{{ number_format($p->unitMargin(), 0, ',', ' ') }}</span>@else — @endif</td>
                                <td class="whitespace-nowrap"><a href="{{ route('pro.manage.stock.movements', ['product' => $p->id]) }}" class="text-xs text-brand-600">Historique</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-ink-400">Aucune pièce. <a href="{{ route('pro.products.create') }}" class="text-brand-600">Ajoutez votre première pièce</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $products->links() }}</div>
        </div>

        {{-- Mouvement de stock --}}
        <form method="POST" action="{{ route('pro.manage.stock.store') }}" class="card h-fit space-y-3 p-5 lg:sticky lg:top-20"
              x-data="{ type: '{{ old('type', 'in') }}', reasons: @js($reasons) }">
            @csrf
            <h2 class="font-bold">Enregistrer un mouvement</h2>
            <div class="grid grid-cols-3 gap-1 rounded-xl bg-ink-50 p-1 text-sm font-medium">
                @foreach (['in' => '⬇ Entrée', 'out' => '⬆ Sortie', 'adjust' => '⚖ Inventaire'] as $t => $label)
                    <label class="cursor-pointer rounded-lg px-2 py-2 text-center" :class="type === '{{ $t }}' ? 'bg-white shadow-sm' : 'text-ink-500'">
                        <input type="radio" name="type" value="{{ $t }}" x-model="type" class="sr-only">{{ $label }}
                    </label>
                @endforeach
            </div>
            <div>
                <label class="field-label" for="product_id">Pièce</label>
                <select id="product_id" name="product_id" class="input" required>
                    <option value="">Choisir…</option>
                    @foreach ($allProducts as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} (stock : {{ $p->stock }})</option>
                    @endforeach
                </select>
                @error('product_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label" for="quantity" x-text="type === 'adjust' ? 'Quantité comptée en boutique' : 'Quantité'">Quantité</label>
                <input id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0" class="input" required>
                @error('quantity') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label" for="reason">Motif</label>
                <select id="reason" name="reason" class="input" required>
                    <template x-for="(label, key) in reasons[type]" :key="key">
                        <option :value="key" x-text="label"></option>
                    </template>
                </select>
                @error('reason') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div x-show="type === 'in'">
                <x-field name="unit_cost" type="number" label="Prix d'achat unitaire (FCFA)" min="0" hint="Met à jour le prix d'achat de la pièce." />
            </div>
            <x-field name="note" label="Note" placeholder="Ex. fournisseur, n° de bon" />
            <button class="btn btn-primary w-full">Enregistrer</button>
            <p class="text-xs text-ink-400">Les ventes faites par facture sortent du stock automatiquement.</p>
        </form>
    </div>
</x-layouts.pro>
