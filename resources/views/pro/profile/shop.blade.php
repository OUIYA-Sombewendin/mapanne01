{{-- Fiche d'une boutique de pièces détachées : ce qu'elle vend, son catalogue, la vente et la livraison. --}}
<x-pro-profile :provider="$provider" :copy="$copy" :completion="$completion" title="Fiche de la boutique" :sections="[
    ['identite', 'shop', 'La boutique'],
    ['vente', 'cube', 'Ce que vous vendez'],
    ['catalogue', 'layers', 'Catalogue'],
    ['localisation', 'map', 'Adresse'],
    ['paiement', 'card', 'Paiement et livraison'],
    ['autres', 'document', 'Autres informations'],
    ['legal', 'shield', 'Entreprise'],
]">
    @include('pro.profile._identity', ['title' => 'La boutique', 'text' => 'Nom, contacts et présentation de la boutique.', 'managerLabel' => 'Gérant de la boutique'])
    @include('pro.profile._details', ['id' => 'vente', 'icon' => 'cube', 'title' => 'Ce que vous vendez', 'text' => 'Véhicules couverts, marques, détail ou gros, occasion, commande sur demande.'])

    <section id="catalogue" class="card scroll-mt-28 p-6">
        @include('pro._section-head', ['icon' => 'layers', 'title' => 'Catalogue', 'text' => 'Vos pièces se gèrent dans le catalogue : prix, stock, photos, compatibilités.'])
        <div class="flex flex-wrap items-center gap-4 rounded-2xl bg-ink-50 p-4">
            <div class="flex-1">
                <p class="text-3xl font-extrabold">{{ $productsCount }}</p>
                <p class="text-sm text-ink-500">pièce(s) au catalogue</p>
            </div>
            @if (auth()->user()->canShop('catalog'))
                <a href="{{ route('pro.products.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="size-4" /> Ajouter une pièce</a>
                @if (feature('catalog_import_enabled'))
                    <a href="{{ route('pro.products.import') }}" class="btn btn-ghost btn-sm">Importer un fichier Excel</a>
                @endif
                <a href="{{ route('pro.products.index') }}" class="btn btn-ghost btn-sm">Voir le catalogue</a>
            @endif
        </div>
        @if ($categories->isNotEmpty())
            <div class="mt-5">
                <p class="field-label">Rayons (affichés dans les résultats de recherche)</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($categories as $category)
                        @foreach ($category->services as $service)
                            <label class="chip chip-filter px-3 py-1.5 text-sm">
                                <input type="checkbox" name="services[]" value="{{ $service->id }}" class="sr-only" @checked(in_array($service->id, old('services', $selectedServices)))>
                                {{ $service->name }}
                            </label>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    @include('pro.profile._location', ['title' => 'Adresse de la boutique'])
    @include('pro.profile._payment', ['title' => 'Paiement et livraison'])
    @include('pro.profile._custom')
    @include('pro.profile._legal', ['title' => 'Entreprise'])
</x-pro-profile>
