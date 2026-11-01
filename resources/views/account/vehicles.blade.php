<x-layouts.app title="Mes véhicules">
    <div class="mb-5">
        <h1 class="text-2xl font-extrabold">🚗 Mes véhicules</h1>
        <p class="text-sm text-ink-500">Vos véhicules servent à trouver les pièces compatibles et, si vous le souhaitez, à recevoir les rappels d'entretien.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
        <section class="space-y-3">
            @forelse ($vehicles as $vehicle)
                <div class="card flex flex-wrap items-center gap-3 p-4">
                    <span class="text-3xl">{{ $vehicle->icon() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{ $vehicle->title() }}</span>
                        <span class="block text-xs text-ink-500">{{ $vehicle->type->label() }} · {{ $vehicle->energyLabel() }}{{ $vehicle->plate ? ' · '.$vehicle->plate : '' }}</span>
                    </span>
                    <span class="flex flex-wrap items-center gap-2">
                        @if (feature('logbook_enabled'))
                            <a href="{{ route('logbook.show', $vehicle) }}" class="btn btn-ghost btn-sm">🛠️ Carnet</a>
                        @endif
                        @if (feature('marketplace_enabled') && $vehicle->brand)
                            <a href="{{ route('products.index', array_filter(['make' => $vehicle->brand, 'model' => $vehicle->model, 'year' => $vehicle->year])) }}" class="btn btn-ghost btn-sm">⚙️ Pièces compatibles</a>
                        @endif
                        <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}" onsubmit="return confirm('Supprimer ce véhicule et son carnet ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-sm text-red-600">Supprimer</button>
                        </form>
                    </span>
                </div>
            @empty
                <div class="card p-8 text-center">
                    <p class="text-4xl">🏍️</p>
                    <p class="mt-2 font-semibold">Aucun véhicule enregistré</p>
                    <p class="mt-1 text-sm text-ink-500">Ajoutez votre moto ou votre voiture avec le formulaire.</p>
                </div>
            @endforelse
        </section>

        <section class="card h-fit p-5">
            <h2 class="mb-3 font-bold">+ Ajouter un véhicule</h2>
            @include('account.logbook._vehicle-form', ['vehicle' => null])
        </section>
    </div>
</x-layouts.app>
