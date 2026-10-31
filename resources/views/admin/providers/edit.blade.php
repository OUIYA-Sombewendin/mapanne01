<x-layouts.admin :title="'Modifier '.$provider->business_name">
    <a href="{{ route('admin.providers.show', $provider) }}" class="text-sm text-ink-500">← Retour à la fiche</a>
    <h1 class="mt-2 mb-4 text-2xl font-extrabold">Modifier « {{ $provider->business_name }} »</h1>

    <form method="POST" action="{{ route('admin.providers.update', $provider) }}" class="grid gap-5 xl:grid-cols-2">
        @csrf @method('PUT')
        <section class="card space-y-4 p-5">
            <x-field name="business_name" label="Nom de l'activité" :value="$provider->business_name" required />
            <div>
                <label class="field-label" for="type">Type</label>
                <select id="type" name="type" class="input">
                    @foreach ($types as $slug => $label)
                        <option value="{{ $slug }}" @selected(old('type', $provider->type->slug) === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="manager_name" label="Responsable" :value="$provider->manager_name" />
            <x-field name="description" type="textarea" label="Présentation" :value="$provider->description" />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="phone" label="Téléphone" :value="$provider->phone" required />
                <x-field name="whatsapp" label="WhatsApp" :value="$provider->whatsapp" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-field name="rccm" label="RCCM" :value="$provider->rccm" />
                <x-field name="ifu" label="IFU" :value="$provider->ifu" />
            </div>
        </section>
        <section class="card space-y-4 p-5">
            <x-location-select :cities="$cities" :city="$provider->city_id" :neighborhood="$provider->neighborhood_id" required />
            <x-field name="address" label="Adresse / repère" :value="$provider->address" />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="latitude" label="Latitude" :value="$provider->latitude" required />
                <x-field name="longitude" label="Longitude" :value="$provider->longitude" required />
            </div>
            @foreach (['is_formal' => 'Entreprise enregistrée', 'night_service' => 'Service de nuit', 'is_featured' => '⭐ À la une'] + (feature('delivery_enabled') ? ['delivery_available' => 'Livraison possible'] : []) as $flag => $label)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $flag }}" value="1" class="checkbox" @checked(old($flag, $provider->{$flag}))> {{ $label }}</label>
            @endforeach
            @if (feature('delivery_enabled'))
                <x-field name="delivery_details" label="Détails de livraison" :value="$provider->delivery_details" />
            @endif
            <fieldset>
                <legend class="field-label">Moyens de paiement</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($paymentMethods as $method)
                        <label class="chip chip-filter px-3 py-1.5 text-sm"><input type="checkbox" name="payment_methods[]" value="{{ $method }}" class="sr-only" @checked(in_array($method, old('payment_methods', (array) $provider->payment_methods)))>{{ $method }}</label>
                    @endforeach
                </div>
            </fieldset>
        </section>
        @if ($provider->profileKind())
            <x-type-details-form :provider="$provider" class="card p-5 xl:col-span-2" />
        @endif
        <div class="xl:col-span-2"><button class="btn btn-primary">Enregistrer</button></div>
    </form>
</x-layouts.admin>
