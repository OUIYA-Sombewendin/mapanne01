<x-layouts.admin title="Villes et quartiers · Admin">
    <h1 class="mb-4 text-2xl font-extrabold">Villes et quartiers</h1>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            @foreach ($cities as $city)
                <details class="card p-4" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer items-center justify-between">
                        <span class="font-semibold">{{ $city->name }} <span class="text-sm font-normal text-ink-400">· {{ $city->neighborhoods_count }} quartier(s)</span>
                            @unless ($city->is_active) <span class="chip bg-ink-100 text-ink-500">désactivée</span> @endunless
                        </span>
                        <span class="flex items-center gap-1">
                            <x-lock-button :model="$city" compact />
                            <form method="POST" action="{{ route('admin.cities.toggle', $city) }}">
                                @csrf
                                <button class="btn btn-ghost btn-sm">{{ $city->is_active ? 'Désactiver' : 'Activer' }}</button>
                            </form>
                        </span>
                    </summary>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($city->neighborhoods as $n)
                            <span class="chip bg-ink-50 text-ink-700" title="{{ $n->latitude }}, {{ $n->longitude }}">{{ $n->name }}</span>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('admin.neighborhoods.store', $city) }}" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-[1fr_7rem_7rem_auto]">
                        @csrf
                        <input name="name" class="input col-span-2 sm:col-span-1" placeholder="Nouveau quartier" required>
                        <input name="latitude" class="input" placeholder="Latitude" inputmode="decimal" required>
                        <input name="longitude" class="input" placeholder="Longitude" inputmode="decimal" required>
                        <button class="btn btn-dark col-span-2 sm:col-span-1">Ajouter</button>
                    </form>
                </details>
            @endforeach
            @if ($errors->any())
                <p class="field-error">{{ $errors->first() }}</p>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.cities.store') }}" class="card h-fit space-y-3 p-5">
            @csrf
            <h2 class="font-bold">Nouvelle ville</h2>
            <input name="name" class="input" placeholder="Nom" required>
            <div class="grid grid-cols-2 gap-2">
                <input name="latitude" class="input" placeholder="Latitude" inputmode="decimal" required>
                <input name="longitude" class="input" placeholder="Longitude" inputmode="decimal" required>
            </div>
            <p class="text-xs text-ink-400">Astuce : clic droit sur openstreetmap.org → « Afficher l'adresse » pour obtenir les coordonnées.</p>
            <button class="btn btn-primary w-full">Ajouter</button>
        </form>
    </div>
</x-layouts.admin>
