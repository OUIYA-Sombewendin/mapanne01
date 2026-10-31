<x-layouts.pro title="Catalogue de pièces">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">Catalogue de pièces</h1>
        <div class="flex gap-2">
            @if (feature('catalog_import_enabled'))
                <a href="{{ route('pro.products.import') }}" class="btn btn-ghost">⬆️ Importer un fichier</a>
            @endif
            <a href="{{ route('pro.products.create') }}" class="btn btn-primary">+ Ajouter une pièce</a>
        </div>
    </div>

    @unless ($provider->isVisible())
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Vous pouvez préparer votre catalogue dès maintenant : il sera visible des clients dès que votre fiche sera validée.
        </div>
    @endunless
    @if (feature('products_premoderation'))
        <div class="mb-4 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-800">Chaque nouvelle pièce est vérifiée par l'équipe avant d'être publiée.</div>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['Produits', $stats['total'], 'text-ink-900'],
            ['En rupture', $stats['out'], 'text-red-600'],
            ['Vues', $stats['views'], 'text-ink-900'],
            ['Contacts reçus', $stats['contacts'], 'text-brand-600'],
        ] as [$label, $value, $color])
            <div class="card p-4"><p class="text-2xl font-extrabold {{ $color }}">{{ $value }}</p><p class="text-xs text-ink-500">{{ $label }}</p></div>
        @endforeach
    </div>

    <form class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Rechercher dans mon catalogue">
        <select name="filter" class="input max-w-52" onchange="this.form.submit()">
            <option value="">Tous les produits</option>
            <option value="out" @selected(request('filter') === 'out')>En rupture</option>
            <option value="inactive" @selected(request('filter') === 'inactive')>Retirés de la vente</option>
            <option value="pending" @selected(request('filter') === 'pending')>En attente de vérification</option>
        </select>
        <button class="btn btn-dark">Filtrer</button>
    </form>

    <div class="space-y-3">
        @forelse ($products as $product)
            @php($availability = $product->availability())
            <article class="card flex flex-wrap items-center gap-3 p-3">
                <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-ink-50 text-2xl">
                    @if ($product->coverUrl()) <img src="{{ $product->coverUrl() }}" alt="" class="size-full object-cover"> @else 📦 @endif
                </div>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('pro.products.edit', $product) }}" class="font-semibold hover:text-brand-600">{{ $product->name }}</a>
                    <p class="text-xs text-ink-400">{{ $product->category->name }} · {{ $product->conditionLabel() }} · {{ $product->views_count }} vue(s) · {{ $product->contacts_count }} contact(s)</p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <span class="chip {{ $availability['class'] }}">{{ $availability['label'] }}</span>
                        @if ($product->discountPercent()) <span class="chip bg-red-50 text-red-700">Promo -{{ $product->discountPercent() }} %</span> @endif
                        @if ($product->isPendingApproval()) <span class="chip bg-amber-100 text-amber-800">En attente de vérification</span> @endif
                        @unless ($product->is_active) <span class="chip bg-ink-100 text-ink-500">Retiré de la vente</span> @endunless
                        @if ($product->is_hidden) <span class="chip bg-red-100 text-red-700">Masqué par l'équipe : {{ $product->hidden_reason }}</span> @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('pro.products.quick', $product) }}" class="flex items-end gap-2">
                    @csrf @method('PATCH')
                    <label class="text-xs text-ink-500">Prix (FCFA)
                        <input type="number" name="price" value="{{ $product->price }}" min="50" class="input w-28 py-1.5">
                    </label>
                    <label class="text-xs text-ink-500">Stock
                        <input type="number" name="stock" value="{{ $product->stock }}" min="0" class="input w-20 py-1.5">
                    </label>
                    <button class="btn btn-ghost btn-sm mb-0.5">OK</button>
                </form>
            </article>
        @empty
            <div class="card p-8 text-center">
                <p class="text-4xl">📦</p>
                <p class="mt-2 font-semibold">Votre catalogue est vide</p>
                <p class="mt-1 text-sm text-ink-500">Ajoutez vos pièces une par une, ou importez tout votre stock depuis un fichier Excel.</p>
                <div class="mt-4 flex justify-center gap-2">
                    <a href="{{ route('pro.products.create') }}" class="btn btn-primary">Ajouter ma première pièce</a>
                    @if (feature('catalog_import_enabled'))
                        <a href="{{ route('pro.products.import') }}" class="btn btn-ghost">Importer un fichier</a>
                    @endif
                </div>
            </div>
        @endforelse
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.pro>
