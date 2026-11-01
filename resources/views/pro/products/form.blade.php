<x-layouts.pro :title="$product->exists ? 'Modifier une pièce' : 'Nouvelle pièce'">
    <div class="mb-4"><x-back :href="route('pro.products.index')" label="Catalogue" smart /></div>
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
                        @if ($category->children->isEmpty())
                            <option value="{{ $category->id }}" @selected(old('part_category_id', $product->part_category_id) == $category->id)>{{ $category->icon }} {{ $category->name }}</option>
                        @else
                            <optgroup label="{{ $category->icon }} {{ $category->name }}">
                                <option value="{{ $category->id }}" @selected(old('part_category_id', $product->part_category_id) == $category->id)>{{ $category->name }} (autre)</option>
                                @foreach ($category->children as $sub)
                                    <option value="{{ $sub->id }}" @selected(old('part_category_id', $product->part_category_id) == $sub->id)>{{ $sub->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
                @error('part_category_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-field name="brand" label="Marque de la pièce" :value="$product->brand" placeholder="Bosch, NGK…" />
                <x-field name="reference" label="Référence" :value="$product->reference" />
            </div>
            <div x-data="barcodeScanner" @scanned="$refs.barcode.value = $event.detail">
                <label class="field-label" for="barcode">Code-barres (facultatif)</label>
                <div class="flex gap-2">
                    <input id="barcode" name="barcode" x-ref="barcode" value="{{ old('barcode', $product->barcode) }}" class="input" placeholder="Scannez ou tapez le code" autocomplete="off">
                    <button type="button" x-show="supported" x-cloak @click="start()" class="btn btn-ghost shrink-0">📷 Scanner</button>
                </div>
                @include('pro._scanner')
                @error('barcode') <p class="field-error">{{ $message }}</p> @enderror
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
            @php($fitmentRows = old('fitments', $product->relationLoaded('fitments') ? $product->fitments->map->only(['make', 'model', 'year_from', 'year_to'])->all() : []))
            <fieldset x-data="{ rows: @js(array_values($fitmentRows ?: [['make' => '', 'model' => '', 'year_from' => '', 'year_to' => '']])) }">
                <legend class="field-label">Véhicules compatibles</legend>
                <p class="mb-2 text-xs text-ink-500">Les clients filtrent par marque, modèle et année : une ligne par modèle. Laissez le modèle vide si la pièce va à toute la marque.</p>
                <datalist id="makes">@foreach ($makes as $make)<option value="{{ $make }}">@endforeach</datalist>
                <template x-for="(row, i) in rows" :key="i">
                    <div class="mb-2 grid grid-cols-[1fr_1fr_4.5rem_4.5rem_auto] items-center gap-1.5">
                        <input :name="`fitments[${i}][make]`" x-model="row.make" list="makes" class="input-sm" placeholder="Marque" aria-label="Marque">
                        <input :name="`fitments[${i}][model]`" x-model="row.model" class="input-sm" placeholder="Modèle" aria-label="Modèle">
                        <input :name="`fitments[${i}][year_from]`" x-model="row.year_from" type="number" min="1960" class="input-sm" placeholder="De" aria-label="Année de début">
                        <input :name="`fitments[${i}][year_to]`" x-model="row.year_to" type="number" min="1960" class="input-sm" placeholder="À" aria-label="Année de fin">
                        <button type="button" @click="rows.splice(i, 1)" class="px-1 text-ink-400 hover:text-red-600" aria-label="Retirer">✕</button>
                    </div>
                </template>
                <button type="button" @click="rows.push({ make: '', model: '', year_from: '', year_to: '' })" class="btn btn-ghost btn-sm">+ Ajouter un véhicule</button>
                @error('fitments.*') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>
            <x-field name="compatibility" label="Autres précisions de compatibilité" :value="$product->compatibility"
                     placeholder="Ex. moteur 1.6 essence uniquement" />

            <h2 class="pt-2 font-bold">Prix et stock</h2>
            <div class="grid grid-cols-2 gap-3">
                <x-field name="price" type="number" label="Prix de vente (FCFA)" :value="$product->price" min="50" step="25" required />
                <div>
                    <label class="field-label" for="unit">Vendu à</label>
                    <select id="unit" name="unit" class="input">
                        @foreach ($units as $key => [$label]) <option value="{{ $key }}" @selected(old('unit', $product->unit ?? 'piece') === $key)>l'unité : {{ $label }}</option> @endforeach
                    </select>
                </div>
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
