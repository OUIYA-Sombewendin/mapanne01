@php
    $kindLabel = \App\Support\ProviderProfiles::get($kind)['label'] ?? null;
@endphp
<x-layouts.admin :title="'Modifier '.$provider->business_name">
    <a href="{{ route('admin.providers.show', $provider) }}" class="text-sm text-ink-500">← Retour à la fiche</a>

    <section class="admin-hero on-color relative mt-2 overflow-hidden rounded-3xl px-6 py-6 text-white shadow-lg sm:px-8">
        <p class="text-sm text-white/70">Modifier la fiche</p>
        <h1 class="text-2xl font-extrabold sm:text-3xl">{{ $provider->type->icon() }} {{ $provider->business_name }}</h1>
        <p class="mt-1 text-white/75">{{ $provider->type->label() }}{{ $kindLabel ? ' · formulaire « '.$kindLabel.' »' : ' · formulaire général' }} · {{ $provider->status->label() }}</p>
    </section>

    <form method="POST" action="{{ route('admin.providers.update', $provider) }}" class="mt-6 grid gap-6 xl:grid-cols-2">
        @csrf @method('PUT')

        <section class="card space-y-4 p-6">
            @include('pro._section-head', ['icon' => 'document', 'title' => 'Identité', 'text' => ''])
            <x-field name="business_name" :label="$copy['name']" :value="$provider->business_name" required />
            <x-field name="manager_name" label="Responsable" :value="$provider->manager_name" />
            <x-field name="description" type="textarea" label="Présentation" :value="$provider->description" />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="phone" label="Téléphone" :value="$provider->phone" required />
                <x-field name="whatsapp" label="WhatsApp" :value="$provider->whatsapp" />
            </div>
        </section>

        <section class="card space-y-4 p-6">
            @include('pro._section-head', ['icon' => 'map', 'title' => 'Localisation', 'text' => ''])
            <x-location-select :cities="$cities" :city="$provider->city_id" :neighborhood="$provider->neighborhood_id" required />
            <x-field name="address" label="Adresse / repère" :value="$provider->address" />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="latitude" label="Latitude" :value="$provider->latitude" required />
                <x-field name="longitude" label="Longitude" :value="$provider->longitude" required />
            </div>
        </section>

        @if ($kind)
            <section class="card p-6 xl:col-span-2">
                @include('pro._section-head', ['icon' => 'wrench', 'title' => $kindLabel.' : informations du métier', 'text' => $copy['details_intro']])
                <x-type-details-form :provider="$provider" :title="false" />
            </section>
        @endif

        <section class="card p-6 xl:col-span-2">
            @include('pro._section-head', ['icon' => 'layers', 'title' => 'Services du métier', 'text' => 'Seuls les services de ce métier sont proposés.'])
            <input type="hidden" name="services_present" value="1">
            <div class="space-y-4">
                @foreach ($categories as $category)
                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold text-ink-700">{{ $category->icon }} {{ $category->name }}</legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($category->services as $service)
                                <label class="chip chip-filter px-3 py-1.5 text-sm">
                                    <input type="checkbox" name="services[]" value="{{ $service->id }}" class="sr-only" @checked(in_array($service->id, old('services', $provider->services->pluck('id')->all())))>{{ $service->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>

        @if ($customFields->isNotEmpty())
            <section class="card p-6 xl:col-span-2">
                @include('pro._section-head', ['icon' => 'document', 'title' => 'Autres informations', 'text' => 'Champs ajoutés dans Formulaires des métiers.'])
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($customFields as $field)
                        @php($value = old('custom.'.$field->key, $provider->custom_fields[$field->key] ?? null))
                        @if ($field->type === 'bool')
                            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="custom[{{ $field->key }}]" value="0"><input type="checkbox" name="custom[{{ $field->key }}]" value="1" class="checkbox" @checked($value)> {{ $field->label }}</label>
                        @elseif ($field->type === 'select')
                            <div>
                                <label class="field-label">{{ $field->label }}</label>
                                <select name="custom[{{ $field->key }}]" class="input"><option value="">—</option>
                                    @foreach ($field->options ?? [] as $o) <option @selected($value === $o)>{{ $o }}</option> @endforeach
                                </select>
                            </div>
                        @elseif ($field->type === 'multi')
                            <fieldset class="sm:col-span-2">
                                <legend class="field-label">{{ $field->label }}</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($field->options ?? [] as $o)
                                        <label class="chip chip-filter px-3 py-1.5 text-sm"><input type="checkbox" name="custom[{{ $field->key }}][]" value="{{ $o }}" class="sr-only" @checked(in_array($o, (array) $value, true))>{{ $o }}</label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @else
                            <x-field :name="'custom['.$field->key.']'" :label="$field->label" :value="$value" :type="$field->type === 'number' ? 'number' : ($field->type === 'textarea' ? 'textarea' : 'text')" />
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        <section class="card space-y-4 p-6">
            @include('pro._section-head', ['icon' => 'shield', 'title' => 'Statut et options', 'text' => ''])
            <div class="grid grid-cols-2 gap-3">
                <x-field name="rccm" label="RCCM" :value="$provider->rccm" />
                <x-field name="ifu" label="IFU" :value="$provider->ifu" />
            </div>
            @foreach (['is_formal' => 'Entreprise enregistrée', 'night_service' => $copy['night'], 'is_featured' => '⭐ À la une'] + (feature('delivery_enabled') && $provider->canSellParts() ? ['delivery_available' => 'Livraison possible'] : []) as $flag => $label)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $flag }}" value="1" class="checkbox" @checked(old($flag, $provider->{$flag}))> {{ $label }}</label>
            @endforeach
            @if (feature('delivery_enabled') && $provider->canSellParts())
                <x-field name="delivery_details" label="Détails de livraison" :value="$provider->delivery_details" />
            @endif
        </section>

        <section class="card space-y-4 p-6">
            @include('pro._section-head', ['icon' => 'card', 'title' => 'Paiement', 'text' => ''])
            <div class="flex flex-wrap gap-2">
                @foreach ($paymentMethods as $method)
                    <label class="chip chip-filter px-3 py-1.5 text-sm"><input type="checkbox" name="payment_methods[]" value="{{ $method }}" class="sr-only" @checked(in_array($method, old('payment_methods', (array) $provider->payment_methods)))>{{ $method }}</label>
                @endforeach
            </div>
        </section>

        {{-- Changement de métier : réservé à l'admin, isolé du reste --}}
        <details class="card p-6 xl:col-span-2">
            <summary class="cursor-pointer font-bold text-red-700">⚠️ Changer le métier de ce prestataire</summary>
            <p class="mt-2 text-sm text-ink-600">À utiliser seulement si l'activité a réellement changé. Les services de l'ancien métier sont retirés ; la fiche métier de l'ancien type est conservée mais n'est plus affichée. Après l'enregistrement, rouvrez ce formulaire pour compléter la fiche du nouveau métier.</p>
            <select name="type" class="input mt-3 max-w-sm">
                @foreach ($types as $slug => $label)
                    <option value="{{ $slug }}" @selected(old('type', $provider->type->slug) === $slug)>{{ $label }}</option>
                @endforeach
            </select>
        </details>

        <div class="xl:col-span-2"><button class="btn btn-primary px-6">Enregistrer</button></div>
    </form>
</x-layouts.admin>
