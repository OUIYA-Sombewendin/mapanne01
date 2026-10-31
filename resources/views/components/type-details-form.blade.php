{{--
    Champs de la fiche métier d'une catégorie (mécanicien, garage, dépanneur, station, boutique),
    générés depuis ProviderProfiles. Noms : details[<catégorie>][<champ>].
    Usage : <x-type-details-form :provider="$p" kind="garage" :only="['capacity']" />  (kind par défaut : celui du type actuel ;
    only : sous-ensemble de champs, pour répartir la fiche métier sur plusieurs sections)
--}}
@props(['provider', 'kind' => null, 'title' => true, 'only' => null])
@php
    $kind ??= $provider->profileKind();
    $definition = \App\Support\ProviderProfiles::get($kind);
    $details = $provider->detailsFor($kind);
    $base = 'details.'.$kind.'.';
@endphp
@if ($definition)
    <section {{ $attributes->class(['space-y-4']) }}>
        @if ($title)
            <h2 class="font-bold">{{ $definition['label'] }} : informations du métier</h2>
        @endif
        @php($fields = $only ? collect($definition['fields'])->only($only) : collect($definition['fields']))
        @php($bools = $fields->where('type', 'bool'))
        @php($others = $fields->where('type', '!=', 'bool'))

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($others as $name => $field)
                @php($value = old($base.$name, $details?->{$name}))
                @if ($field['type'] === 'multi')
                    <fieldset class="sm:col-span-2">
                        <legend class="field-label">{{ $field['label'] }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($field['options'] as $optKey => $optLabel)
                                <label class="chip chip-filter px-3 py-1.5 text-sm">
                                    <input type="checkbox" name="details[{{ $kind }}][{{ $name }}][]" value="{{ $optKey }}" class="sr-only" @checked(in_array($optKey, (array) $value, true))>{{ $optLabel }}
                                </label>
                            @endforeach
                        </div>
                        @error($base.$name.'.*') <p class="field-error">{{ $message }}</p> @enderror
                    </fieldset>
                @else
                    <x-field :name="'details['.$kind.']['.$name.']'" :label="$field['label']" :value="$value"
                             :type="in_array($field['type'], ['int', 'decimal'], true) ? 'number' : 'text'"
                             :step="$field['type'] === 'decimal' ? '0.1' : null" min="0"
                             :placeholder="$field['placeholder'] ?? null" />
                @endif
            @endforeach
        </div>

        @if ($bools->isNotEmpty())
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($bools as $name => $field)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-100 px-3 py-2.5 text-sm hover:border-ink-300">
                        <input type="hidden" name="details[{{ $kind }}][{{ $name }}]" value="0">
                        <input type="checkbox" name="details[{{ $kind }}][{{ $name }}]" value="1" class="peer sr-only" @checked(old($base.$name, $details?->{$name}))>
                        <span class="relative h-5 w-9 shrink-0 rounded-full bg-ink-200 transition peer-checked:bg-brand-500 after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
                        {{ $field['label'] }}
                    </label>
                @endforeach
            </div>
        @endif
    </section>
@endif
