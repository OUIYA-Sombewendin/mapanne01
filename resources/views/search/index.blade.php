<x-layouts.app title="Trouver un professionnel" wide>
    @php($activeFilters = collect($filters)->only(['service', 'city', 'neighborhood'])->filter()->count())

    <form action="{{ route('search') }}" method="GET" x-data="autoFilter({ filters: false })" class="mb-4">
        <input type="hidden" name="lat" value="{{ $filters['lat'] }}">
        <input type="hidden" name="lng" value="{{ $filters['lng'] }}">

        <div class="flex gap-2">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-ink-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                <input name="q" value="{{ $filters['q'] }}" class="input pl-10" placeholder="Ex. ma moto ne démarre pas, crevaison, vidange…">
            </div>
            <button type="button" @click="filters = !filters" class="btn btn-ghost relative">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                <span class="hidden sm:inline">Filtres</span>
                @if ($activeFilters)
                    <span class="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-brand-500 text-[10px] text-white">{{ $activeFilters }}</span>
                @endif
            </button>
            <button class="btn btn-primary">Chercher</button>
        </div>

        {{-- Position : géolocalisation ou ville/quartier --}}
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm" x-data="geoSearch">
            <button type="button" @click="locate()" :disabled="loading" class="chip border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm text-blue-700">
                <span x-text="loading ? 'Localisation…' : '📍 Autour de moi'">📍 Autour de moi</span>
            </button>
            @if ($hasCenter)
                <span class="text-ink-500">Près de <strong class="text-ink-800">{{ $center['label'] }}</strong>
                    @if (empty($center['city_only'])) · {{ $filters['radius'] }} km @endif
                </span>
                <a href="{{ route('search', collect($filters)->except(['lat', 'lng', 'city', 'neighborhood'])->filter()->all()) }}" class="text-xs text-ink-400 underline">effacer</a>
            @endif
            <p x-cloak x-show="error" x-text="error" class="w-full text-red-600"></p>
        </div>

        {{-- Ce que la recherche a compris (« ma moto ne démarre pas ») --}}
        @if ($understood['problems'] || $understood['vehicle'])
            <p class="mt-3 rounded-xl bg-sky-50 px-3 py-2 text-sm text-sky-900 ring-1 ring-sky-200">
                🔎 Nous cherchons les pros pour :
                @foreach ($understood['problems'] as $p) <strong>{{ \App\Support\SearchProblems::label($p) }}</strong>@if (! $loop->last), @endif @endforeach
                @if ($understood['vehicle']) · <strong>{{ \App\Enums\VehicleType::from($understood['vehicle'])->label() }}</strong> @endif
            </p>
        @endif

        {{-- Filtres principaux, toujours visibles : un geste suffit sur téléphone --}}
        <div class="mt-3 space-y-2">
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                <label class="chip chip-filter shrink-0 px-3 py-2 text-sm"><input type="radio" name="type" value="" class="sr-only" @checked(! $filters['type'])>Tous</label>
                @foreach ($types as $type)
                    <label class="chip chip-filter shrink-0 px-3 py-2 text-sm"><input type="radio" name="type" value="{{ $type->value }}" class="sr-only" @checked($filters['type'] === $type->value)>{{ $type->icon() }} {{ $type->shortLabel() }}</label>
                @endforeach
            </div>
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                @foreach ($vehicles as $value => $label)
                    <label class="chip chip-filter shrink-0 px-3 py-2 text-sm"><input type="radio" name="vehicle" value="{{ $value }}" class="sr-only" @checked($filters['vehicle'] === $value)>{{ ['moto' => '🛵', 'voiture' => '🚗', 'tricycle' => '🛺', 'camion' => '🚚'][$value] ?? '' }} {{ $label }}</label>
                @endforeach
                @if ($filters['vehicle'])
                    <a href="{{ route('search', collect(request()->query())->except(['vehicle', 'page'])->all()) }}" class="chip shrink-0 px-3 py-2 text-sm text-ink-500 underline">Tous véhicules</a>
                @endif
                <span class="mx-1 w-px shrink-0 bg-ink-200"></span>
                <label class="chip chip-filter shrink-0 px-3 py-2 text-sm"><input type="checkbox" name="open_now" value="1" class="sr-only" @checked($filters['open_now'])>🟢 Ouvert maintenant</label>
                <label class="chip chip-filter shrink-0 px-3 py-2 text-sm"><input type="checkbox" name="available" value="1" class="sr-only" @checked($filters['available'])>✅ Disponible</label>
                <label class="chip chip-filter shrink-0 px-3 py-2 text-sm"><input type="checkbox" name="night" value="1" class="sr-only" @checked($filters['night'])>🌙 Service de nuit</label>
                <label class="chip chip-filter shrink-0 px-3 py-2 text-sm"><input type="checkbox" name="verified" value="1" class="sr-only" @checked($filters['verified'])>✓ Vérifiés</label>
                <label class="chip chip-filter shrink-0 px-3 py-2 text-sm"><input type="checkbox" name="electric" value="1" class="sr-only" @checked($filters['electric'])>⚡ Spécialiste électrique</label>
                        </div>
            @if (! $filters['q'] && ! $filters['problem'] && ! $filters['service'])
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 text-xs">
                    <span class="shrink-0 self-center text-ink-500">Votre problème :</span>
                    @foreach (\App\Support\SearchProblems::ALL as $key => [$label, $icon])
                        <a href="{{ route('search', ['problem' => $key] + collect(request()->query())->except(['page'])->all()) }}" class="chip shrink-0 bg-ink-50 px-3 py-1.5 text-ink-700 hover:bg-brand-50">{{ $icon }} {{ $label }}</a>
                    @endforeach
                </div>
            @elseif ($filters['problem'])
                <input type="hidden" name="problem" value="{{ $filters['problem'] }}">
                <p class="text-sm">Problème : <strong>{{ \App\Support\SearchProblems::label($filters['problem']) }}</strong>
                    <a href="{{ route('search', collect(request()->query())->except(['problem', 'page'])->all()) }}" class="ml-1 text-xs text-ink-400 underline">retirer</a></p>
            @endif
        </div>

        <div x-cloak x-show="filters" x-transition class="card mt-3 space-y-4 p-4">
            <x-location-select :cities="$cities" :city="$filters['city']" :neighborhood="$filters['neighborhood']"
                               city-name="city" neighborhood-name="neighborhood" any-label="Toutes les villes" />

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="service">Service</label>
                    <select id="service" name="service" class="input">
                        <option value="">Tous</option>
                        @foreach ($categories as $category)
                            <optgroup label="{{ $category->name }}">
                                @foreach ($category->services as $service)
                                    <option value="{{ $service->slug }}" @selected($filters['service'] === $service->slug)>{{ $service->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="radius">Distance max.</label>
                    <select id="radius" name="radius" class="input">
                        @foreach ($radii as $r)
                            <option value="{{ $r }}" @selected($filters['radius'] === $r)>{{ $r }} km</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('search') }}" class="btn btn-ghost">Réinitialiser</a>
                <button class="btn btn-primary">Appliquer</button>
            </div>
        </div>
    </form>

    <div x-data="{ tab: 'list' }" class="lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-5">
        {{-- Onglets mobile --}}
        <div class="mb-3 grid grid-cols-2 rounded-xl bg-ink-100 p-1 text-sm font-semibold lg:hidden">
            <button @click="tab = 'list'" :class="tab === 'list' ? 'bg-white shadow-sm' : 'text-ink-500'" class="rounded-lg py-2">Liste ({{ $providers->total() }})</button>
            <button @click="tab = 'map'; $dispatch('map-shown')" :class="tab === 'map' ? 'bg-white shadow-sm' : 'text-ink-500'" class="rounded-lg py-2">Carte</button>
        </div>

        <section :class="tab === 'list' ? '' : 'hidden lg:block'">
            <p class="mb-3 hidden text-sm text-ink-500 lg:block">{{ $providers->total() }} résultat{{ $providers->total() > 1 ? 's' : '' }}</p>

            <div class="space-y-3">
                @forelse ($providers as $provider)
                    <x-provider-card :provider="$provider" :now="$now" />
                @empty
                    <div class="card p-8 text-center">
                        <p class="text-4xl">🔎</p>
                        <p class="mt-2 font-semibold">Aucun professionnel trouvé</p>
                        <p class="mt-1 text-sm text-ink-500">Essayez d'agrandir la distance ou de retirer des filtres.</p>
                        @if ($hasCenter && $filters['radius'] < 50)
                            <a href="{{ request()->fullUrlWithQuery(['radius' => 50]) }}" class="btn btn-primary mt-4">Chercher jusqu'à 50 km</a>
                        @endif
                    </div>
                @endforelse
            </div>

            <div class="mt-4">{{ $providers->links() }}</div>
        </section>

        <section :class="tab === 'map' ? '' : 'hidden lg:block'">
            <div x-data="resultsMap(@js($markers), @js($center), @js($hasCenter))" @map-shown.window="refresh()"
                 class="card overflow-hidden lg:sticky lg:top-20">
                <div x-ref="map" class="h-[65dvh] w-full lg:h-[calc(100dvh-7rem)]"></div>
            </div>
        </section>
    </div>
</x-layouts.app>
