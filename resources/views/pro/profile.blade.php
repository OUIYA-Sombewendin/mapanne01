<x-layouts.app title="Ma fiche">
    <h1 class="mb-4 text-2xl font-extrabold">Ma fiche professionnelle</h1>
    @include('pro._nav')

    <form method="POST" action="{{ route('pro.profile.update') }}" enctype="multipart/form-data" class="grid gap-5 lg:grid-cols-2">
        @csrf @method('PUT')

        <section class="card space-y-4 p-5">
            <h2 class="font-bold">Identité</h2>
            <x-field name="business_name" label="Nom de l'activité" :value="$provider->business_name" required />
            <div>
                <label class="field-label" for="type">Type d'activité</label>
                <select id="type" name="type" class="input">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $provider->type->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="manager_name" label="Nom du responsable" :value="$provider->manager_name" />
            <x-field name="description" type="textarea" label="Présentation" :value="$provider->description" placeholder="Votre expérience, vos spécialités, les marques que vous connaissez…" />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="phone" type="tel" label="Téléphone" :value="$provider->phone" required />
                <x-field name="whatsapp" type="tel" label="WhatsApp" :value="$provider->whatsapp" hint="Si différent" />
            </div>

            <div class="flex items-center gap-3">
                <div class="flex size-16 items-center justify-center overflow-hidden rounded-xl bg-brand-50 text-2xl">
                    @if ($provider->logoUrl()) <img src="{{ $provider->logoUrl() }}" alt="" class="size-full object-cover"> @else {{ $provider->type->icon() }} @endif
                </div>
                <div class="flex-1">
                    <label class="field-label" for="logo">Logo</label>
                    <input id="logo" type="file" name="logo" accept="image/*" class="text-sm">
                    @error('logo') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-3 rounded-xl bg-ink-50 p-4" x-data="{ formal: {{ old('is_formal', $provider->is_formal) ? 'true' : 'false' }} }">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="hidden" name="is_formal" value="0">
                    <input type="checkbox" name="is_formal" value="1" x-model="formal" class="checkbox"> Entreprise enregistrée (RCCM)
                </label>
                <div x-show="formal" x-cloak class="grid grid-cols-2 gap-3">
                    <x-field name="rccm" label="N° RCCM" :value="$provider->rccm" />
                    <x-field name="ifu" label="N° IFU" :value="$provider->ifu" />
                </div>
                <p class="text-xs text-ink-500">Les activités informelles sont les bienvenues : ce champ donne seulement droit au badge « Entreprise enregistrée ».</p>
            </div>

            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="hidden" name="night_service" value="0">
                <input type="checkbox" name="night_service" value="1" class="checkbox" @checked(old('night_service', $provider->night_service))> 🌙 J'interviens la nuit
            </label>

            <fieldset>
                <legend class="field-label">Moyens de paiement acceptés</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($paymentMethods as $method)
                        <label class="chip chip-filter px-3 py-1.5 text-sm">
                            <input type="checkbox" name="payment_methods[]" value="{{ $method }}" class="sr-only" @checked(in_array($method, old('payment_methods', (array) $provider->payment_methods)))>{{ $method }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            @if (feature('delivery_enabled') && $provider->canSellParts())
                <div class="space-y-2 rounded-xl bg-ink-50 p-4" x-data="{ delivery: {{ old('delivery_available', $provider->delivery_available) ? 'true' : 'false' }} }">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" name="delivery_available" value="1" x-model="delivery" class="checkbox"> 🛵 Je livre les pièces
                    </label>
                    <div x-show="delivery" x-cloak>
                        <x-field name="delivery_details" label="Zones, délais et frais" :value="$provider->delivery_details" placeholder="Ex. Ouaga centre en 2 h, 1 000 FCFA" />
                    </div>
                </div>
            @endif
        </section>

        @if ($provider->profileKind())
            <x-type-details-form :provider="$provider" class="card p-5 lg:col-span-2" />
        @endif

        <section class="card space-y-4 p-5" x-data="locationPicker({{ old('latitude', $provider->latitude) ?? 'null' }}, {{ old('longitude', $provider->longitude) ?? 'null' }})"
                 @location-changed="moveTo($event.detail)">
            <h2 class="font-bold">Localisation</h2>
            <x-location-select :cities="$cities" :city="$provider->city_id" :neighborhood="$provider->neighborhood_id" required />
            <x-field name="address" label="Adresse / repère" :value="$provider->address" placeholder="Ex. Face à la pharmacie du Progrès" />

            <div>
                <div class="mb-1 flex items-center justify-between">
                    <span class="field-label mb-0">Position exacte</span>
                    <button type="button" @click="useMyPosition()" :disabled="loading" class="btn btn-ghost btn-sm" x-text="loading ? 'Localisation…' : '📍 Je suis sur place'"></button>
                </div>
                <div x-ref="map" class="h-72 overflow-hidden rounded-xl ring-1 ring-ink-100"></div>
                <p class="mt-1 text-xs text-ink-400">Touchez la carte ou déplacez l'épingle sur votre atelier.</p>
                <p x-cloak x-show="error" x-text="error" class="field-error"></p>
                @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
                <input type="hidden" name="latitude" :value="lat">
                <input type="hidden" name="longitude" :value="lng">
            </div>
        </section>

        <section class="card p-5 lg:col-span-2">
            <h2 class="mb-3 font-bold">Services proposés</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $category)
                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold text-ink-700">{{ $category->icon }} {{ $category->name }}</legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($category->services as $service)
                                <label class="chip chip-filter px-3 py-1.5 text-sm">
                                    <input type="checkbox" name="services[]" value="{{ $service->id }}" class="sr-only" @checked(in_array($service->id, old('services', $selectedServices)))>
                                    {{ $service->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <div class="sticky bottom-20 z-10 lg:col-span-2 md:bottom-4">
            <button class="btn btn-primary w-full py-3 shadow-lg md:w-auto">Enregistrer ma fiche</button>
        </div>
    </form>

    {{-- Photos (formulaire séparé : envoi indépendant) --}}
    <section class="card mt-5 p-5">
        <h2 class="mb-1 font-bold">Photos de l'atelier</h2>
        <p class="mb-3 text-sm text-ink-500">Jusqu'à {{ setting('max_photos') }} photos. Des photos réelles rassurent les clients.</p>
        <div class="mb-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
            @foreach ($provider->photos as $photo)
                <div class="group relative aspect-square overflow-hidden rounded-xl">
                    <img src="{{ $photo->url() }}" alt="" class="size-full object-cover">
                    <form method="POST" action="{{ route('pro.photos.destroy', $photo) }}" class="absolute top-1 right-1">
                        @csrf @method('DELETE')
                        <button class="flex size-7 items-center justify-center rounded-full bg-black/60 text-white" aria-label="Supprimer">✕</button>
                    </form>
                </div>
            @endforeach
        </div>
        @if ($provider->photos->count() < setting('max_photos'))
            <form method="POST" action="{{ route('pro.photos.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="file" name="photos[]" accept="image/*" multiple required class="text-sm">
                <button class="btn btn-dark btn-sm">Ajouter</button>
            </form>
            @error('photos') <p class="field-error">{{ $message }}</p> @enderror
            @error('photos.*') <p class="field-error">{{ $message }}</p> @enderror
        @endif
    </section>
</x-layouts.app>
