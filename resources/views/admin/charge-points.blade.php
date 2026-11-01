@php($p = $edit)
<x-layouts.admin title="Points de recharge">
    <h1 class="mb-1 text-2xl font-extrabold">⚡ Points de recharge et d'échange de batterie</h1>
    <p class="mb-5 text-sm text-ink-500">Affichés sur la carte de la page publique <a href="{{ route('electric') }}" target="_blank" class="text-accent-700 underline">Moto et voiture électriques</a>. Les réparateurs spécialisés y apparaissent dès que leur justificatif « Spécialiste électrique » est validé.</p>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        <section class="card overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Point</th><th>Type</th><th>Véhicules</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    @forelse ($points as $point)
                        <tr @class(['bg-accent-50/50' => $p?->id === $point->id])>
                            <td><span class="font-semibold">{{ $point->name }}</span><br><span class="text-xs text-ink-400">{{ $point->city?->name }}{{ $point->address ? ' · '.$point->address : '' }}</span></td>
                            <td class="whitespace-nowrap">{{ $point->icon() }} {{ $point->kindLabel() }}</td>
                            <td class="text-xs">{{ implode(', ', $point->vehicleLabels()) ?: '—' }}</td>
                            <td><span @class(['chip', 'bg-emerald-100 text-emerald-800' => $point->is_active, 'bg-ink-100 text-ink-500' => ! $point->is_active])>{{ $point->is_active ? 'Affiché' : 'Masqué' }}</span></td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('admin.charge-points.index', ['modifier' => $point->id]) }}" class="btn btn-ghost btn-sm">Modifier</a>
                                <form method="POST" action="{{ route('admin.charge-points.destroy', $point) }}" class="inline" onsubmit="return confirm('Supprimer ce point ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-red-700">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-ink-400">Aucun point pour l'instant. Ajoutez les stations de recharge et d'échange de batterie que vous connaissez.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card p-5" x-data="locationPicker({{ old('latitude', $p?->latitude) ?? 'null' }}, {{ old('longitude', $p?->longitude) ?? 'null' }})">
            <h2 class="mb-3 text-lg font-extrabold" style="color: var(--heading)">{{ $p ? 'Modifier « '.$p->name.' »' : 'Ajouter un point' }}</h2>
            <form method="POST" action="{{ $p ? route('admin.charge-points.update', $p) : route('admin.charge-points.store') }}" class="space-y-3">
                @csrf
                @if ($p) @method('PUT') @endif
                <x-field name="name" label="Nom" :value="$p?->name" placeholder="Ex. Station d'échange Ouaga 2000" required />
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Type</label>
                        <select name="kind" class="input">
                            @foreach ($kinds as $key => [$label, $icon]) <option value="{{ $key }}" @selected(old('kind', $p?->kind) === $key)>{{ $icon }} {{ $label }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Ville</label>
                        <select name="city_id" class="input" @change="const o = $event.target.selectedOptions[0]; o.dataset.lat && moveTo({ lat: +o.dataset.lat, lng: +o.dataset.lng }, 13)">
                            <option value="">—</option>
                            @foreach ($cities as $c) <option value="{{ $c->id }}" data-lat="{{ $c->latitude }}" data-lng="{{ $c->longitude }}" @selected(old('city_id', $p?->city_id) == $c->id)>{{ $c->name }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <x-field name="address" label="Adresse / repère" :value="$p?->address" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="phone" type="tel" label="Téléphone" :value="$p?->phone" />
                    <x-field name="hours" label="Horaires" :value="$p?->hours" placeholder="Ex. 7h – 21h, 7j/7" />
                </div>
                <div>
                    <span class="field-label">Véhicules acceptés</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($vehicles as $key => $label)
                            <label class="chip chip-filter px-3 py-2 text-sm"><input type="checkbox" name="vehicles[]" value="{{ $key }}" class="sr-only" @checked(in_array($key, old('vehicles', $p?->vehicles ?? ['moto']), true))>{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <x-field name="notes" label="Note (prix, marque de batterie…)" :value="$p?->notes" />
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <span class="field-label mb-0">Position</span>
                        <button type="button" @click="useMyPosition()" :disabled="loading" class="btn btn-ghost btn-sm" x-text="loading ? 'Localisation…' : '📍 Je suis sur place'"></button>
                    </div>
                    <div x-ref="map" class="h-64 overflow-hidden rounded-xl ring-1 ring-ink-100"></div>
                    <p class="mt-1 text-xs text-ink-400">Touchez la carte ou déplacez l'épingle.</p>
                    @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
                    <input type="hidden" name="latitude" :value="lat">
                    <input type="hidden" name="longitude" :value="lng">
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $p?->is_active ?? true))> Afficher sur la page publique
                </label>
                <div class="flex gap-2">
                    <button class="btn btn-primary">{{ $p ? 'Enregistrer' : 'Ajouter' }}</button>
                    @if ($p) <a href="{{ route('admin.charge-points.index') }}" class="btn btn-ghost">Annuler</a> @endif
                </div>
            </form>
        </section>
    </div>
</x-layouts.admin>
