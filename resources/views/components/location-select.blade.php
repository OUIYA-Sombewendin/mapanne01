@props(['cities', 'city' => null, 'neighborhood' => null, 'cityName' => 'city_id', 'neighborhoodName' => 'neighborhood_id', 'required' => false, 'anyLabel' => 'Choisir…'])
@php
    $payload = $cities->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'latitude' => $c->latitude, 'longitude' => $c->longitude,
        'neighborhoods' => $c->neighborhoods->map(fn ($n) => ['id' => $n->id, 'name' => $n->name, 'latitude' => $n->latitude ?? null, 'longitude' => $n->longitude ?? null])->values(),
    ])->values();
@endphp
<div x-data="locationSelect(@js($payload), @js(old($cityName, $city)), @js(old($neighborhoodName, $neighborhood)))" {{ $attributes->class('grid grid-cols-2 gap-3') }}>
    <div>
        <label class="field-label" for="{{ $cityName }}">Ville</label>
        <select id="{{ $cityName }}" name="{{ $cityName }}" x-model="cityId" @change="cityChanged()" @required($required) @class(['input', 'input-error' => $errors->has($cityName)])>
            <option value="">{{ $anyLabel }}</option>
            <template x-for="c in cities" :key="c.id">
                <option :value="String(c.id)" x-text="c.name" :selected="String(c.id) === cityId"></option>
            </template>
        </select>
        @error($cityName) <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="field-label" for="{{ $neighborhoodName }}">Quartier</label>
        <select id="{{ $neighborhoodName }}" name="{{ $neighborhoodName }}" x-model="neighborhoodId" @change="neighborhoodChanged()" :disabled="!neighborhoods.length" class="input disabled:bg-ink-50">
            <option value="" x-text="neighborhoods.length ? 'Tous les quartiers' : '—'"></option>
            <template x-for="n in neighborhoods" :key="n.id">
                <option :value="String(n.id)" x-text="n.name" :selected="String(n.id) === neighborhoodId"></option>
            </template>
        </select>
        @error($neighborhoodName) <p class="field-error">{{ $message }}</p> @enderror
    </div>
</div>
