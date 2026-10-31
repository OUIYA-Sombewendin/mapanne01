{{--
    Identité. Paramètres : $id, $title, $text, $copy (libellés du métier),
    $managerLabel (facultatif), $detailFields (champs de la fiche métier à afficher ici, ex. ['brand']).
--}}
<section id="{{ $id ?? 'identite' }}" class="card scroll-mt-28 p-6">
    @include('pro._section-head', ['icon' => 'document', 'title' => $title, 'text' => $text ?? ''])
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-field name="business_name" :label="$copy['name']" :value="$provider->business_name" required :placeholder="$copy['name_hint']" />
        </div>
        @if (! empty($detailFields))
            <div class="sm:col-span-2"><x-type-details-form :provider="$provider" :title="false" :only="$detailFields" /></div>
        @endif
        <x-field name="manager_name" :label="$managerLabel ?? 'Nom du responsable'" :value="$provider->manager_name" placeholder="Prénom et nom" />
        <div class="flex items-center gap-3">
            <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand-50 text-2xl">
                @if ($provider->logoUrl()) <img src="{{ $provider->logoUrl() }}" alt="" class="size-full object-cover"> @else {{ $provider->type->icon() }} @endif
            </div>
            <div class="min-w-0 flex-1">
                <label class="field-label" for="logo">{{ $copy['logo'] }}</label>
                <input id="logo" type="file" name="logo" accept="image/*" class="w-full text-sm">
                @error('logo') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="sm:col-span-2">
            <x-field name="description" type="textarea" label="Présentation" :value="$provider->description" :placeholder="$copy['about']" />
        </div>
        <x-field name="phone" type="tel" :label="$phoneLabel ?? 'Téléphone (appels)'" :value="$provider->phone" required />
        <x-field name="whatsapp" type="tel" label="WhatsApp" :value="$provider->whatsapp" hint="Laisser vide si c'est le même numéro." />
    </div>
</section>
