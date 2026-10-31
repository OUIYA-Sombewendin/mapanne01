<x-layouts.app :title="$currentCategory ? $currentCategory->name.' · Pièces détachées' : 'Pièces détachées'" wide>
    @php($activeFilters = collect($filters)->only(['vehicle', 'condition', 'in_stock', 'price_min', 'price_max'])->filter(fn ($v) => $v !== null && $v !== false)->count())

    <div class="mb-4">
        <h1 class="text-2xl font-extrabold">{{ $currentCategory ? $currentCategory->icon.' '.$currentCategory->name : '⚙️ Pièces détachées' }}</h1>
        <p class="text-sm text-ink-500">Trouvez la pièce et la boutique la plus proche, puis contactez-la directement.</p>
    </div>

    <form action="{{ route('products.index') }}" method="GET" x-data="{ filters: {{ $activeFilters ? 'true' : 'false' }} }">
        <input type="hidden" name="lat" value="{{ $filters['lat'] }}">
        <input type="hidden" name="lng" value="{{ $filters['lng'] }}">
        @if ($filters['category']) <input type="hidden" name="category" value="{{ $filters['category'] }}"> @endif

        <div class="flex gap-2">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-ink-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                <input name="q" value="{{ $filters['q'] }}" class="input pl-10" placeholder="Pièce, marque, modèle (ex. plaquette corolla)">
            </div>
            <button type="button" @click="filters = !filters" class="btn btn-ghost relative" aria-label="Filtres">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                @if ($activeFilters)
                    <span class="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-brand-500 text-[10px] text-white">{{ $activeFilters }}</span>
                @endif
            </button>
            <button class="btn btn-primary">Chercher</button>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm" x-data="geoSearch">
            <button type="button" @click="locate()" :disabled="loading" class="chip border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm text-blue-700">
                <span x-text="loading ? 'Localisation…' : '📍 Boutiques proches de moi'">📍 Boutiques proches de moi</span>
            </button>
            @if ($center)
                <span class="text-ink-500">Près de <strong class="text-ink-800">{{ $center['label'] }}</strong></span>
            @endif
            <select name="sort" class="input ml-auto w-auto py-1.5" onchange="this.form.submit()" aria-label="Trier">
                @foreach ($sorts as $value => $label)
                    @continue($value === 'distance' && ! $center)
                    <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p x-cloak x-show="error" x-text="error" class="w-full text-red-600"></p>
        </div>

        <div x-cloak x-show="filters" x-transition class="card mt-3 space-y-4 p-4">
            <x-location-select :cities="$cities" :city="$filters['city']" :neighborhood="$filters['neighborhood']"
                               city-name="city" neighborhood-name="neighborhood" any-label="Toutes les villes" />
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <span class="field-label">Véhicule</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($vehicleTypes as $value => $label)
                            <label class="chip chip-filter px-3 py-1.5 text-sm"><input type="radio" name="vehicle" value="{{ $value }}" class="sr-only" @checked($filters['vehicle'] === $value)>{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <span class="field-label">État</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($conditions as $value => $label)
                            <label class="chip chip-filter px-3 py-1.5 text-sm"><input type="radio" name="condition" value="{{ $value }}" class="sr-only" @checked($filters['condition'] === $value)>{{ $label }}</label>
                        @endforeach
                        <label class="chip chip-filter px-3 py-1.5 text-sm"><input type="checkbox" name="in_stock" value="1" class="sr-only" @checked($filters['in_stock'])>Disponible</label>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:w-1/2">
                <x-field name="price_min" type="number" label="Prix min. (FCFA)" :value="$filters['price_min']" min="0" />
                <x-field name="price_max" type="number" label="Prix max. (FCFA)" :value="$filters['price_max']" min="0" />
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('products.index', array_filter(['category' => $filters['category']])) }}" class="btn btn-ghost">Réinitialiser</a>
                <button class="btn btn-primary">Appliquer</button>
            </div>
        </div>
    </form>

    {{-- Catégories --}}
    <div class="-mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-2">
        <a href="{{ route('products.index', collect($filters)->except(['category', 'sort'])->filter()->all()) }}"
           @class(['chip shrink-0 px-3 py-2 text-sm', 'bg-ink-800 text-white' => ! $currentCategory, 'bg-white text-ink-700 ring-1 ring-ink-200' => $currentCategory])>Toutes</a>
        @foreach ($categories as $category)
            <a href="{{ route('products.index', ['category' => $category->slug] + collect($filters)->except(['category', 'sort'])->filter()->all()) }}"
               @class(['chip shrink-0 px-3 py-2 text-sm', 'bg-ink-800 text-white' => $currentCategory?->is($category), 'bg-white text-ink-700 ring-1 ring-ink-200' => ! $currentCategory?->is($category)])>
                {{ $category->icon }} {{ $category->name }}
            </a>
        @endforeach
    </div>

    {{-- Raccourcis vers les pièces compatibles avec les véhicules enregistrés du client --}}
    @if ($myVehicles->isNotEmpty())
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="text-ink-500">Pour mon véhicule :</span>
            @foreach ($myVehicles as $v)
                <a href="{{ route('products.index', ['q' => trim($v->brand.' '.$v->model), 'vehicle' => $v->type->value]) }}" class="chip bg-sky-50 px-3 py-1.5 text-sky-800 ring-1 ring-sky-200">🚘 {{ $v->title() }}</a>
            @endforeach
        </div>
    @endif

    <p class="mt-3 mb-3 text-sm text-ink-500">{{ $products->total() }} pièce{{ $products->total() > 1 ? 's' : '' }}</p>

    {{-- Demande de pièce : réservée aux clients (les visiteurs sont invités à se connecter) --}}
    @php($requestsOn = feature('part_requests_enabled') && (! auth()->check() || auth()->user()->isClient()))
    @if ($products->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-4xl">🔎</p>
            <p class="mt-2 font-semibold">Aucune pièce trouvée</p>
            <p class="mt-1 text-sm text-ink-500">Essayez un autre mot (ex. « plaquette » plutôt que « plaquettes de frein avant ») ou retirez des filtres.</p>
            @if ($requestsOn)
                <a href="{{ route('part-requests.create', ['q' => $filters['q']]) }}" class="btn btn-primary mt-4">Demander cette pièce aux boutiques</a>
            @endif
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        <div class="mt-4">{{ $products->links() }}</div>
        @if ($requestsOn)
            <div class="card mt-5 flex flex-wrap items-center gap-3 p-4">
                <span class="text-2xl">📣</span>
                <p class="flex-1 text-sm"><strong>Vous ne trouvez pas la bonne pièce ?</strong> Décrivez-la et laissez les boutiques vous proposer leur prix.</p>
                <a href="{{ route('part-requests.create', ['q' => $filters['q']]) }}" class="btn btn-dark btn-sm">Faire une demande</a>
            </div>
        @endif
    @endif
</x-layouts.app>
