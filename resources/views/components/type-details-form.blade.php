{{-- Champs de la fiche métier (mécanicien, garage, dépanneur, station, boutique), générés depuis ProviderProfiles. --}}
@props(['provider'])
@php
    $kind = $provider->profileKind();
    $definition = \App\Support\ProviderProfiles::get($kind);
    $details = $provider->details();
@endphp
@if ($definition)
    <section {{ $attributes->class(['space-y-3']) }}>
        <h2 class="font-bold">{{ $definition['label'] }} : informations du métier</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($definition['fields'] as $name => $field)
                @php($value = old('details.'.$name, $details?->{$name}))
                @switch($field['type'])
                    @case('bool')
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="details[{{ $name }}]" value="0">
                            <input type="checkbox" name="details[{{ $name }}]" value="1" class="checkbox" @checked($value)> {{ $field['label'] }}
                        </label>
                        @break
                    @case('multi')
                        <fieldset class="sm:col-span-2">
                            <legend class="field-label">{{ $field['label'] }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($field['options'] as $optKey => $optLabel)
                                    <label class="chip chip-filter px-3 py-1.5 text-sm">
                                        <input type="checkbox" name="details[{{ $name }}][]" value="{{ $optKey }}" class="sr-only" @checked(in_array($optKey, (array) $value, true))>{{ $optLabel }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        @break
                    @default
                        <x-field :name="'details['.$name.']'" :label="$field['label']" :value="$value"
                                 :type="in_array($field['type'], ['int', 'decimal'], true) ? 'number' : 'text'"
                                 :step="$field['type'] === 'decimal' ? '0.1' : null" min="0"
                                 :placeholder="$field['placeholder'] ?? null" />
                @endswitch
            @endforeach
        </div>
    </section>
@endif
