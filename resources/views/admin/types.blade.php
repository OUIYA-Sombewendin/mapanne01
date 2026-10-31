<x-layouts.admin title="Types de prestataires">
    <h1 class="mb-1 text-2xl font-extrabold">Types de prestataires</h1>
    <p class="mb-5 text-sm text-ink-500">Ajoutez un métier (vulcanisateur, laveur, électricien auto…) sans toucher au code. Chaque type est rattaché à une fiche métier (table dédiée : mécanicien, garage, dépanneur, station, boutique). Un type désactivé n'est plus proposé à l'inscription ni dans les filtres, mais les fiches existantes restent en ligne.</p>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-3">
            @foreach ($types as $type)
                <form method="POST" action="{{ route('admin.types.update', $type) }}" class="card grid gap-3 p-5 sm:grid-cols-[4rem_minmax(0,1fr)_minmax(0,1fr)_5rem] sm:items-end">
                    <h2 class="flex flex-wrap items-center gap-2 sm:col-span-4">
                        <span class="text-xl">{{ $type->icon() }}</span> {{ $type->name }}
                        <span @class(['chip', 'bg-emerald-100 text-emerald-800' => $type->is_active, 'bg-ink-100 text-ink-500' => ! $type->is_active])>{{ $type->is_active ? 'Actif' : 'Inactif' }}</span>
                        @if ($type->can_sell_parts) <span class="chip bg-brand-100 text-brand-800">Vend des pièces</span> @endif
                        <span class="chip bg-white text-accent-700 ring-1 ring-accent-100">{{ \App\Support\ProviderProfiles::options()[$type->profile_kind] ?? 'Formulaire général' }}</span>
                        <span class="ml-auto text-xs font-normal text-ink-500">{{ $type->providers_count }} fiche(s)</span>
                    </h2>
                    @csrf @method('PUT')
                    <div><label class="field-label">Icône</label><input name="icon" value="{{ $type->icon }}" class="input text-center text-xl" maxlength="8" required></div>
                    <div><label class="field-label">Nom</label><input name="name" value="{{ $type->name }}" class="input" required></div>
                    <div><label class="field-label">Nom court</label><input name="short_name" value="{{ $type->short_name }}" class="input"></div>
                    <div><label class="field-label">Ordre</label><input type="number" name="position" value="{{ $type->position }}" class="input"></div>
                    <div class="flex flex-wrap items-center gap-4 text-sm sm:col-span-4">
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked($type->is_active)> Actif</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="can_sell_parts" value="1" class="checkbox" @checked($type->can_sell_parts)> Peut vendre des pièces (catalogue)</label>
                        <select name="profile_kind" class="input w-auto py-1.5 text-sm" title="Informations propres au métier">
                            <option value="">Fiche métier : aucune</option>
                            @foreach (\App\Support\ProviderProfiles::options() as $k => $label)
                                <option value="{{ $k }}" @selected($type->profile_kind === $k)>Fiche métier : {{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="text-ink-400">{{ $type->providers_count }} fiche(s) · code <code>{{ $type->slug }}</code></span>
                        <button class="btn btn-ghost btn-sm ml-auto">Enregistrer</button>
                    </div>
                </form>
                <div class="-mt-2 flex justify-end"><x-lock-button :model="$type" /></div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.types.store') }}" class="card h-fit space-y-3 p-5">
            @csrf
            <h2 class="font-bold">Nouveau type</h2>
            <div class="grid grid-cols-[4rem_1fr] gap-2">
                <input name="icon" class="input text-center text-xl" placeholder="🛞" maxlength="8" required>
                <input name="name" class="input" placeholder="Vulcanisateur" required>
            </div>
            <input name="short_name" class="input" placeholder="Nom court (facultatif)">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox" checked> Actif</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_sell_parts" value="1" class="checkbox"> Peut vendre des pièces</label>
            <select name="profile_kind" class="input " title="Informations propres au métier">
                            <option value="">Fiche métier : aucune</option>
                            @foreach (\App\Support\ProviderProfiles::options() as $k => $label)
                                <option value="{{ $k }}" @selected(null === $k)>Fiche métier : {{ $label }}</option>
                            @endforeach
                        </select>
            <p class="text-xs text-ink-400">La fiche métier ajoute les champs propres à l'activité (ex. un vulcanisateur peut utiliser la fiche « Mécanicien »).</p>
            <button class="btn btn-primary w-full">Ajouter</button>
        </form>
    </div>
</x-layouts.admin>
