{{-- Adresse, carte et service de nuit. Paramètres : $id, $title, $text, $copy, $detailFields (champs métier à afficher ici). --}}
<section id="{{ $id ?? 'localisation' }}" class="card scroll-mt-28 p-6"
         x-data="locationPicker({{ old('latitude', $provider->latitude) ?? 'null' }}, {{ old('longitude', $provider->longitude) ?? 'null' }}, {{ $provider->locksFields() && $provider->latitude ? 'true' : 'false' }})"
         @location-changed="moveTo($event.detail)">
    @include('pro._section-head', ['icon' => 'map', 'title' => $title, 'text' => $text ?? $copy['place']])
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2"><x-location-select :cities="$cities" :city="$provider->city_id" :neighborhood="$provider->neighborhood_id" required /></div>
        <div class="sm:col-span-2"><x-field name="address" label="Adresse / repère" :value="$provider->address" placeholder="Ex. face à la pharmacie du Progrès" /></div>
    </div>
    <div class="mt-4">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <span class="field-label mb-0">Position exacte</span>
            <button type="button" x-show="locked" x-cloak @click="unlock()" class="text-xs font-semibold text-accent-700 hover:underline">🔒 Position vérifiée · demander un déplacement</button>
        </div>
        <x-map-tools />
        <div x-ref="map" class="h-80 overflow-hidden rounded-xl ring-1 ring-ink-100"></div>
        @if ($provider->locksFields())
            @if (array_key_exists('latitude', $provider->requestedChanges()))
                <p class="mt-1 text-xs font-medium text-amber-700">⏳ Un déplacement de la position est en attente de vérification par l’équipe.</p>
            @endif
            <p x-show="!locked" x-cloak class="mt-1 text-xs text-ink-500">La nouvelle position sera appliquée après vérification par l’équipe.</p>
        @endif
        <p x-cloak x-show="error" x-text="error" class="field-error"></p>
        @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
        <input type="hidden" name="latitude" :value="lat">
        <input type="hidden" name="longitude" :value="lng">
    </div>
    @if (! empty($detailFields))
        <div class="mt-5"><x-type-details-form :provider="$provider" :title="false" :only="$detailFields" /></div>
    @endif
    <label class="mt-5 flex cursor-pointer items-center gap-3 rounded-xl bg-ink-50 px-4 py-3 text-sm font-medium">
        <input type="hidden" name="night_service" value="0">
        <input type="checkbox" name="night_service" value="1" class="peer sr-only" @checked(old('night_service', $provider->night_service))>
        <span class="relative h-5 w-9 shrink-0 rounded-full bg-ink-200 transition peer-checked:bg-brand-500 after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
        {{ $copy['night'] }}
        <a href="{{ route('pro.schedule') }}" class="ml-auto text-xs text-brand-600">Régler les horaires →</a>
    </label>
</section>
