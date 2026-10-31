<x-layouts.pro :title="$product->exists ? 'Modifier une pièce' : 'Nouvelle pièce'">
    <a href="{{ route('pro.products.index') }}" class="text-sm text-ink-500">← Catalogue</a>
    <h1 class="mt-2 mb-4 text-2xl font-extrabold">{{ $product->exists ? $product->name : 'Nouvelle pièce' }}</h1>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $product->exists ? route('pro.products.update', $product) : route('pro.products.store') }}"
          class="grid gap-5 lg:grid-cols-2">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <section class="card space-y-4 p-5">
            <h2 class="font-bold">La pièce</h2>
            <x-field name="name" label="Nom de la pièce" :value="$product->name" placeholder="Ex. Plaquettes de frein avant" required />
            <div>
                <label class="field-label" for="part_category_id">Catégorie</label>
                <select id="part_category_id" name="part_category_id" class="input" required>
                    <option value="">Choisir…</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('part_category_id', $product->part_category_id) == $category->id)>{{ $category->icon }} {{ $category->name }}</option>
                    @endforeach
                </select>
                @error('part_category_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-field name="brand" label="Marque de la pièce" :value="$product->brand" placeholder="Bosch, NGK…" />
                <x-field name="reference" label="Référence" :value="$product->reference" />
            </div>
            <x-field name="description" type="textarea" label="Description" :value="$product->description" />

            <fieldset>
                <legend class="field-label">État</legend>
                <div class="flex gap-2">
                    @foreach ($conditions as $value => $label)
                        <label class="chip chip-filter px-4 py-2 text-sm">
                            <input type="radio" name="condition" value="{{ $value }}" class="sr-only" @checked(old('condition', $product->condition) === $value)> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        <section class="card space-y-4 p-5">
            <h2 class="font-bold">Compatibilité</h2>
            <fieldset>
                <legend class="field-label">Pour quels véhicules ?</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($vehicleTypes as $value => $label)
                        <label class="chip chip-filter px-4 py-2 text-sm">
                            <input type="checkbox" name="vehicle_types[]" value="{{ $value }}" class="sr-only" @checked(in_array($value, old('vehicle_types', $product->vehicle_types ?? [])))> {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('vehicle_types') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>
            <x-field name="compatibility" label="Marques et modèles compatibles" :value="$product->compatibility"
                     placeholder="Ex. Toyota Corolla 2008-2014, Yaris" hint="Les clients cherchent souvent par modèle : soyez précis." />

            <h2 class="pt-2 font-bold">Prix et stock</h2>
            <div class="grid grid-cols-2 gap-3">
                <x-field name="price" type="number" label="Prix de vente (FCFA)" :value="$product->price" min="50" step="25" required />
                <x-field name="compare_at_price" type="number" label="Ancien prix (promo)" :value="$product->compare_at_price" min="50" step="25" hint="Facultatif : affiché barré." />
                <x-field name="stock" type="number" label="Quantité en stock" :value="$product->stock ?? 0" min="0" required />
                <x-field name="warranty" label="Garantie" :value="$product->warranty" placeholder="Ex. 3 mois" />
            </div>
            <div class="grid grid-cols-3 gap-3 rounded-xl bg-ink-50 p-3">
                <x-field name="cost_price" type="number" label="Prix d'achat" :value="$product->cost_price" min="0" hint="Privé : sert à calculer la marge." />
                <x-field name="stock_alert" type="number" label="Alerte stock" :value="$product->stock_alert" min="0" placeholder="Ex. 2" hint="Prévenu sous ce seuil." />
                <x-field name="shelf" label="Emplacement" :value="$product->shelf" placeholder="Ex. Rayon A" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="on_order" value="0">
                <input type="checkbox" name="on_order" value="1" class="checkbox" @checked(old('on_order', $product->on_order))> Disponible sur commande quand le stock est à zéro
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $product->is_active))> Publié sur le marketplace (sinon : visible seulement dans votre inventaire)
            </label>
        </section>

        <section class="card p-5 lg:col-span-2">
            <h2 class="mb-1 font-bold">Photos</h2>
            <p class="mb-3 text-sm text-ink-500">Jusqu'à {{ $maxPhotos }} photos. La première sert de vignette.</p>
            @if ($product->exists && $product->photos->isNotEmpty())
                <div class="mb-3 grid grid-cols-4 gap-2 sm:w-1/2">
                    @foreach ($product->photos as $photo)
                        <div class="relative aspect-square overflow-hidden rounded-xl">
                            <img src="{{ $photo->url() }}" alt="" class="size-full object-cover">
                            <button form="delete-photo-{{ $photo->id }}" class="absolute top-1 right-1 flex size-6 items-center justify-center rounded-full bg-black/60 text-xs text-white" aria-label="Supprimer">✕</button>
                        </div>
                    @endforeach
                </div>
            @endif
            @if (! $product->exists || $product->photos->count() < $maxPhotos)
                <input type="file" name="photos[]" accept="image/*" multiple class="text-sm">
            @endif
            @error('photos') <p class="field-error">{{ $message }}</p> @enderror
            @error('photos.*') <p class="field-error">{{ $message }}</p> @enderror
        </section>

        <div class="flex flex-wrap items-center gap-2 lg:col-span-2">
            <button class="btn btn-primary py-3">{{ $product->exists ? 'Enregistrer' : 'Ajouter au catalogue' }}</button>
            @if ($product->exists)
                <button form="delete-product" class="btn btn-ghost text-red-600" onclick="return confirm('Supprimer définitivement cette pièce ?')">Supprimer</button>
                <a href="{{ route('products.show', $product) }}" class="ml-auto text-sm text-brand-600" target="_blank">Voir la page publique ↗</a>
            @endif
        </div>
    </form>

    {{-- Formulaires de suppression hors du formulaire principal (pas de formulaires imbriqués). --}}
    @if ($product->exists)
        <form id="delete-product" method="POST" action="{{ route('pro.products.destroy', $product) }}">@csrf @method('DELETE')</form>
        @foreach ($product->photos as $photo)
            <form id="delete-photo-{{ $photo->id }}" method="POST" action="{{ route('pro.products.photos.destroy', $photo) }}">@csrf @method('DELETE')</form>
        @endforeach
    @endif
</x-layouts.pro>
