<x-layouts.admin title="Marketplace">
    <h1 class="mb-1 text-2xl font-extrabold">Marketplace de pièces</h1>
    <p class="mb-4 text-sm text-ink-500">
        Pré-modération des produits : <strong>{{ feature('products_premoderation') ? 'activée' : 'désactivée' }}</strong> ·
        Demandes de pièces : <strong>{{ feature('part_requests_enabled') ? 'activées' : 'désactivées' }}</strong>
        (<a href="{{ route('admin.settings.index', 'marketplace') }}" class="text-brand-600">réglages</a>).
    </p>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="space-y-5">
            <x-filter-bar :keys="['q', 'category', 'filter', 'shop', 'condition', 'stock', 'price_min', 'price_max']" class="!mb-0">
                <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-48" placeholder="Nom, marque, référence">
                <select name="category" class="input-sm">
                    <option value="">Toutes catégories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <select name="shop" class="input-sm max-w-48">
                    <option value="">Toutes les boutiques</option>
                    @foreach ($shops as $s) <option value="{{ $s->id }}" @selected(request('shop') == $s->id)>{{ $s->business_name }}</option> @endforeach
                </select>
                <select name="filter" class="input-sm">
                    <option value="">Modération : tous</option>
                    <option value="pending" @selected(request('filter') === 'pending')>En attente ({{ $pendingCount }})</option>
                    <option value="hidden" @selected(request('filter') === 'hidden')>Masqués</option>
                    <option value="featured" @selected(request('filter') === 'featured')>À la une</option>
                </select>
                <select name="condition" class="input-sm">
                    <option value="">Neuf et occasion</option>
                    @foreach (\App\Models\Product::CONDITIONS as $k => $l) <option value="{{ $k }}" @selected(request('condition') === $k)>{{ $l }}</option> @endforeach
                </select>
                <select name="stock" class="input-sm">
                    <option value="">Stock : tous</option>
                    <option value="in" @selected(request('stock') === 'in')>En stock</option>
                    <option value="out" @selected(request('stock') === 'out')>En rupture</option>
                </select>
                <input type="number" name="price_min" value="{{ request('price_min') }}" class="input-sm w-28" placeholder="Prix min." min="0">
                <input type="number" name="price_max" value="{{ request('price_max') }}" class="input-sm w-28" placeholder="Prix max." min="0">
            </x-filter-bar>

            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr>
                        <x-sort-th column="name" label="Produit" />
                        <x-sort-th column="shop" label="Boutique" />
                        <x-sort-th column="price" label="Prix" />
                        <x-sort-th column="stock" label="Stock" />
                        <x-sort-th column="views" label="Vues / contacts" />
                        <th>Modération</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($products as $p)
                            <tr @class(['opacity-60' => $p->is_hidden])>
                                <td>
                                    <a href="{{ route('products.show', $p) }}" target="_blank" class="font-medium hover:text-brand-600">{{ $p->name }}</a>
                                    @if ($p->is_featured) ⭐ @endif
                                    <p class="text-xs text-ink-400">{{ $p->category->name }} · {{ $p->conditionLabel() }}@unless ($p->is_active) · retiré par le vendeur @endunless</p>
                                    @if ($p->isPendingApproval()) <span class="chip bg-amber-100 text-amber-800">en attente</span> @endif
                                </td>
                                <td><a href="{{ route('admin.providers.show', $p->provider) }}" class="text-brand-600">{{ $p->provider->business_name }}</a></td>
                                <td class="whitespace-nowrap">{{ $p->priceLabel() }}
                                    @if ($p->discountPercent()) <span class="chip bg-red-50 text-red-700">-{{ $p->discountPercent() }} %</span> @endif</td>
                                <td>{{ $p->stock }}</td>
                                <td class="whitespace-nowrap">{{ $p->views_count }} / {{ $p->contacts_count }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <x-lock-button :model="$p" compact />
                                        @if ($p->isPendingApproval())
                                            <form method="POST" action="{{ route('admin.products.approve', $p) }}">@csrf<button class="btn btn-primary btn-sm">Publier</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.products.featured', $p) }}">@csrf<button class="btn btn-ghost btn-sm" title="À la une">{{ $p->is_featured ? '☆' : '⭐' }}</button></form>
                                        @if ($p->is_hidden)
                                            <form method="POST" action="{{ route('admin.products.restore', $p) }}">@csrf<button class="btn btn-ghost btn-sm">Republier</button></form>
                                        @else
                                            <form method="POST" action="{{ route('admin.products.hide', $p) }}" class="flex gap-1">@csrf
                                                <input name="reason" class="input w-32 py-1 text-xs" placeholder="Motif" required>
                                                <button class="btn btn-danger btn-sm">Masquer</button>
                                            </form>
                                        @endif
                                    </div>
                                    @if ($p->is_hidden) <p class="mt-1 text-xs text-red-600">{{ $p->hidden_reason }}</p> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-ink-400">Aucun produit.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $products->links() }}</div>

            <section class="card p-5">
                <h2 class="mb-3 font-bold">Dernières demandes de pièces</h2>
                <ul class="divide-y divide-ink-50 text-sm">
                    @forelse ($requests as $r)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span><strong>{{ $r->title }}</strong> <span class="text-ink-400">· {{ $r->user->name }} · {{ $r->city?->name }} · {{ $r->offers_count }} offre(s) · {{ $r->statusLabel() }}</span></span>
                            @if ($r->isOpen())
                                <form method="POST" action="{{ route('admin.part-requests.close', $r) }}">@csrf<button class="btn btn-ghost btn-sm">Clôturer</button></form>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-ink-400">Aucune demande.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="card p-5">
                <h2 class="mb-2 font-bold">Catégories</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($categories as $c)
                        <li class="flex justify-between"><span>{{ $c->icon }} {{ $c->name }}</span><span class="text-ink-400">{{ $c->products_count }}</span></li>
                    @endforeach
                </ul>
            </section>
            <form method="POST" action="{{ route('admin.part-categories.store') }}" class="card space-y-2 p-5">
                @csrf
                <h2 class="font-bold">Nouvelle catégorie</h2>
                <div class="grid grid-cols-[4rem_1fr] gap-2">
                    <input name="icon" class="input text-center" placeholder="🔩" maxlength="8">
                    <input name="name" class="input" placeholder="Nom" required>
                </div>
                <button class="btn btn-dark w-full">Ajouter</button>
            </form>
        </aside>
    </div>
</x-layouts.admin>
