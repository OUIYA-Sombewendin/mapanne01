{{-- Champs ajoutés par l'administration pour ce métier (Référentiels › Formulaires des métiers). --}}
@php($customFields = \App\Models\ProfileField::forProvider($provider))
@if ($customFields->isNotEmpty())
    <section id="autres" class="card scroll-mt-28 p-6">
        @include('pro._section-head', ['icon' => 'document', 'title' => 'Autres informations', 'text' => 'Informations demandées par l\'équipe '.setting('site_name').' pour votre métier.'])
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($customFields as $field)
                @php($value = old('custom.'.$field->key, $provider->custom_fields[$field->key] ?? null))
                @php($name = 'custom['.$field->key.']')
                @switch($field->type)
                    @case('bool')
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-100 px-3 py-2.5 text-sm hover:border-ink-300">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($value)>
                            <span class="relative h-5 w-9 shrink-0 rounded-full bg-ink-200 transition peer-checked:bg-brand-500 after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
                            {{ $field->label }}
                        </label>
                        @break
                    @case('select')
                        <div>
                            <label class="field-label" for="custom-{{ $field->key }}">{{ $field->label }}@if ($field->is_required) *@endif</label>
                            <select id="custom-{{ $field->key }}" name="{{ $name }}" class="input">
                                <option value="">—</option>
                                @foreach ($field->options ?? [] as $option) <option @selected($value === $option)>{{ $option }}</option> @endforeach
                            </select>
                            @if ($field->help) <p class="mt-1 text-xs text-ink-400">{{ $field->help }}</p> @endif
                            @error('custom.'.$field->key) <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        @break
                    @case('multi')
                        <fieldset class="sm:col-span-2">
                            <legend class="field-label">{{ $field->label }}@if ($field->is_required) *@endif</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($field->options ?? [] as $option)
                                    <label class="chip chip-filter px-3 py-1.5 text-sm"><input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" class="sr-only" @checked(in_array($option, (array) $value, true))>{{ $option }}</label>
                                @endforeach
                            </div>
                            @if ($field->help) <p class="mt-1 text-xs text-ink-400">{{ $field->help }}</p> @endif
                            @error('custom.'.$field->key) <p class="field-error">{{ $message }}</p> @enderror
                        </fieldset>
                        @break
                    @default
                        <div @class(['sm:col-span-2' => $field->type === 'textarea'])>
                            <x-field :name="$name" :label="$field->label.($field->is_required ? ' *' : '')" :value="$value"
                                     :type="$field->type === 'textarea' ? 'textarea' : ($field->type === 'number' ? 'number' : 'text')"
                                     :placeholder="$field->placeholder" :hint="$field->help" />
                        </div>
                @endswitch
            @endforeach
        </div>
    </section>
@endif
