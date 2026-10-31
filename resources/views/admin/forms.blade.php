<x-layouts.admin title="Formulaires des métiers">
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-lg sm:px-10">
        <h1 class="flex items-center gap-3 text-2xl font-extrabold sm:text-4xl"><x-icon name="document" class="size-9" stroke-width="1.5" /> Formulaires des métiers</h1>
        <span class="mt-3 block h-1 w-28 rounded-full bg-brand-500"></span>
        <p class="mt-4 max-w-3xl text-white/80">Chaque métier a son propre formulaire de fiche. Ajoutez-y vos champs (texte, nombre, oui/non, listes) : ils apparaissent tout de suite dans la section « Autres informations » des pros concernés, et sur leur fiche publique si vous le souhaitez.</p>
    </section>

    {{-- Choix du métier --}}
    <div class="mt-6 flex flex-wrap gap-2">
        @foreach ($types as $type)
            <a href="{{ route('admin.forms.index', ['type' => $type->slug]) }}" @class([
                'chip px-3 py-2 text-sm',
                'on-color bg-ink-800 text-white' => $current === $type->slug,
                'bg-white text-ink-700 ring-1 ring-ink-200 hover:ring-ink-300' => $current !== $type->slug,
            ])>{{ $type->icon() }} {{ $type->label() }} @unless ($type->is_active) <span class="opacity-60">(inactif)</span> @endunless</a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
            {{-- Champs du métier livrés avec la plateforme --}}
            <section class="card p-6">
                <h2 class="text-lg font-bold">{{ $currentType?->icon() }} Formulaire « {{ $currentType?->label() }} »</h2>
                @if ($builtIn)
                    <p class="mb-4 text-sm text-ink-500">Fiche métier « {{ $builtIn['label'] }} » : champs fournis par la plateforme, enregistrés dans leur propre table.
                        Le rattachement se règle dans <a href="{{ route('admin.types.index') }}" class="text-brand-600 underline">Types de prestataires</a>.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($builtIn['fields'] as $key => $f)
                            <div class="flex items-center justify-between gap-2 rounded-xl bg-ink-50 px-3 py-2 text-sm">
                                <span>{{ $f['label'] }}</span>
                                <span class="chip bg-white text-ink-500 ring-1 ring-ink-100">{{ ['bool' => 'oui/non', 'int' => 'nombre', 'decimal' => 'nombre', 'multi' => 'choix multiples', 'string' => 'texte'][$f['type']] ?? $f['type'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-ink-500">Ce type n'a pas de fiche métier : il utilise le formulaire général. Rattachez-le à une fiche métier dans <a href="{{ route('admin.types.index') }}" class="text-brand-600 underline">Types de prestataires</a>, ou ajoutez ses champs ci-dessous.</p>
                @endif
            </section>

            {{-- Champs ajoutés pour ce métier --}}
            <section class="card p-6">
                <h2 class="mb-1 text-lg font-bold">Champs ajoutés pour ce métier</h2>
                <p class="mb-4 text-sm text-ink-500">Affichés dans « Autres informations » du formulaire {{ $currentType?->label() }}.</p>
                <div class="space-y-3">
                    @forelse ($fields as $field)
                        @include('admin._form-field', ['field' => $field])
                    @empty
                        <p class="rounded-xl border border-dashed border-ink-200 p-4 text-center text-sm text-ink-400">Aucun champ ajouté pour ce métier.</p>
                    @endforelse
                </div>
            </section>

            {{-- Champs communs --}}
            <section class="card p-6">
                <h2 class="mb-1 text-lg font-bold">Champs communs à tous les métiers</h2>
                <p class="mb-4 text-sm text-ink-500">Ajoutés au formulaire de chaque prestataire, quel que soit son métier.</p>
                <div class="space-y-3">
                    @forelse ($shared as $field)
                        @include('admin._form-field', ['field' => $field])
                    @empty
                        <p class="rounded-xl border border-dashed border-ink-200 p-4 text-center text-sm text-ink-400">Aucun champ commun.</p>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Ajout d'un champ --}}
        <form method="POST" action="{{ route('admin.forms.store') }}" class="card h-fit space-y-3 p-6 xl:sticky xl:top-[104px]" x-data="{ type: '{{ old('type', 'text') }}' }">
            @csrf
            <h2 class="text-lg font-bold">Ajouter un champ</h2>
            <div>
                <label class="field-label" for="provider_type">Formulaire</label>
                <select id="provider_type" name="provider_type" class="input">
                    <option value="{{ $current }}">{{ $currentType?->icon() }} {{ $currentType?->label() }} uniquement</option>
                    <option value="">Tous les métiers</option>
                </select>
            </div>
            <x-field name="label" label="Question / libellé" required placeholder="Ex. Marques de pneus vendues" />
            <div>
                <label class="field-label" for="type">Type de réponse</label>
                <select id="type" name="type" x-model="type" class="input">
                    @foreach ($fieldTypes as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div x-show="type === 'select' || type === 'multi'" x-cloak>
                <x-field name="options" type="textarea" label="Choix possibles (un par ligne)" rows="3" />
            </div>
            <x-field name="placeholder" label="Exemple affiché (facultatif)" />
            <x-field name="help" label="Aide (facultative)" />
            <div class="space-y-2 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="is_required" value="1" class="checkbox"> Réponse obligatoire</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_public" value="1" class="checkbox" checked> Affiché sur la fiche publique</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="checkbox" checked> Actif</label>
            </div>
            <button class="btn btn-primary w-full">Ajouter au formulaire</button>
        </form>
    </div>
</x-layouts.admin>
