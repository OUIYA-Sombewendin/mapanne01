{{-- Statut légal. Paramètres : $id, $title, $text. --}}
<section id="{{ $id ?? 'legal' }}" class="card scroll-mt-28 p-6" x-data="{ formal: {{ old('is_formal', $provider->is_formal) ? 'true' : 'false' }} }">
    @include('pro._section-head', ['icon' => 'shield', 'title' => $title ?? 'Statut légal', 'text' => $text ?? 'Facultatif : les activités informelles sont les bienvenues. Ces numéros apparaissent sur vos factures. Pour le badge « Entreprise vérifiée », envoyez le document dans « Badges de confiance ».'])
    <label class="flex cursor-pointer items-center gap-3 text-sm font-medium">
        <input type="hidden" name="is_formal" value="0">
        <input type="checkbox" name="is_formal" value="1" x-model="formal" class="peer sr-only">
        <span class="relative h-5 w-9 shrink-0 rounded-full bg-ink-200 transition peer-checked:bg-brand-500 after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
        Entreprise enregistrée (RCCM)
    </label>
    <div x-show="formal" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
        <div x-data="{ edit: {{ $provider->locksFields() ? 'false' : 'true' }} }" >
            <x-field name="rccm" label="N° RCCM" :value="$provider->rccm" placeholder="BF-OUA-…" :readonly="$provider->locksFields()" x-bind:readonly="!edit" />
            @include('pro.profile._lock-note', ['field' => 'rccm'])
        </div>
        <x-field name="ifu" label="N° IFU" :value="$provider->ifu" />
    </div>
</section>
