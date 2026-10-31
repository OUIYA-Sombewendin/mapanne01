<x-layouts.app title="Trouver un professionnel" wide>
    @php($activeFilters = collect($filters)->only(['type', 'service', 'open_now', 'night', 'available'])->filter()->count())

    <form action="{{ route('search') }}" method="GET" x-data="autoFilter({ filters: false })" class="mb-4">
        <input type="hidden" name="lat" value="{{ $filters['lat'] }}">
        <input type="hidden" name="lng" value="{{ $filters['lng'] }}">

        <div class="flex gap-2">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-ink-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                <input name="q" value="{{ $filters['q'] }}" class="input pl-10" placeholder="Nom, service… (ex. crevaison)">
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

        <div x-cloak x-show="filters" x-transition class="card mt-3 space-y-4 p-4">
            <x-location-select :cities="$cities" :city="$filters['city']" :neighborhood="$filters['neighborhood']"
                               city-name="city" neighborhood-name="neighborhood" any-label="Toutes les villes" />

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="field-label" for="type">Type</label>
                    <select id="type" name="type" class="input">
                        <option value="">Tous</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->icon() }} {{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
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

            <div class="flex flex-wrap gap-2">
                <label class="chip chip-filter px-3 py-2 text-sm"><input type="checkbox" name="open_now" value="1" class="sr-only" @checked($filters['open_now'])>🟢 Ouvert maintenant</label>
                <label class="chip chip-filter px-3 py-2 text-sm"><input type="checkbox" name="available" value="1" class="sr-only" @checked($filters['available'])>⚡ Disponible</label>
                <label class="chip chip-filter px-3 py-2 text-sm"><input type="checkbox" name="night" value="1" class="sr-only" @checked($filters['night'])>🌙 Service de nuit</label>
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
