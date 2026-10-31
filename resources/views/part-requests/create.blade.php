<x-layouts.app title="Demander une pièce">
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-extrabold">Je ne trouve pas ma pièce</h1>
        <p class="mt-1 mb-5 text-sm text-ink-500">Décrivez-la : les boutiques de votre ville vous envoient leur prix. Gratuit et sans engagement.</p>

        <form method="POST" action="{{ route('part-requests.store') }}" enctype="multipart/form-data" class="card space-y-4 p-5">
            @csrf
            <x-field name="title" label="Pièce recherchée" :value="$prefill['title']" placeholder="Ex. Rétroviseur gauche" required />

            <div>
                <label for="vehicle" class="field-label">Véhicule</label>
                <input id="vehicle" name="vehicle" list="my-vehicles" value="{{ old('vehicle', $vehicles->first()?->title()) }}" class="input" placeholder="Ex. Toyota Corolla 2010">
                <datalist id="my-vehicles">
                    @foreach ($vehicles as $v)<option value="{{ $v->title() }}">@endforeach
                </datalist>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="field-label" for="vehicle_type">Type</label>
                    <select id="vehicle_type" name="vehicle_type" class="input">
                        <option value="">—</option>
                        @foreach ($vehicleTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('vehicle_type', $vehicles->first()?->type->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="part_category_id">Catégorie</label>
                    <select id="part_category_id" name="part_category_id" class="input">
                        <option value="">Je ne sais pas</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('part_category_id') == $c->id)>{{ $c->icon }} {{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <x-field name="details" type="textarea" label="Précisions" placeholder="Référence, côté, état souhaité, budget…" />

            <div>
                <label class="field-label" for="photo">Photo de la pièce (facultatif)</label>
                <input id="photo" type="file" name="photo" accept="image/*" capture="environment" class="text-sm">
                @error('photo') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="field-label" for="city_id">Ville</label>
                    <select id="city_id" name="city_id" class="input" required>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}" @selected(old('city_id', auth()->user()->city_id) == $city->id)>{{ $city->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-field name="contact_phone" type="tel" label="Téléphone / WhatsApp" :value="auth()->user()->phone"
                         :hint="auth()->user()->phone ? null : 'Obligatoire pour être rappelé.'" />
            </div>

            <button class="btn btn-primary w-full py-3">Publier ma demande</button>
            <p class="text-center text-xs text-ink-400">Votre demande reste visible {{ setting('part_request_days') }} jours. Votre numéro n'est montré qu'aux boutiques validées.</p>
        </form>
    </div>
</x-layouts.app>
