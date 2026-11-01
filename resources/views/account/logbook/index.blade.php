<x-layouts.app title="Mon carnet d'entretien">
    <div class="mb-5">
        <h1 class="text-2xl font-extrabold">🛠️ Mon carnet d'entretien</h1>
        <p class="text-sm text-ink-500">Vos rappels de vidange, d'assurance et de visite technique, et l'historique de vos réparations.</p>
    </div>

    @if ($vehicles->isEmpty())
        <section class="card mx-auto max-w-xl p-6">
            <h2 class="text-lg font-bold">Ajoutez votre premier véhicule</h2>
            <p class="mb-4 text-sm text-ink-500">Moto, voiture, tricycle ou camion, thermique ou électrique. On vous propose ensuite les bons rappels.</p>
            @include('account.logbook._vehicle-form', ['vehicle' => null])
        </section>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($vehicles as $vehicle)
                <a href="{{ route('logbook.show', $vehicle) }}" class="card block p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <span class="text-4xl">{{ $vehicle->icon() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold">{{ $vehicle->title() }}</p>
                            <p class="text-xs text-ink-500">{{ $vehicle->type->label() }} · {{ $vehicle->energyLabel() }}{{ $vehicle->plate ? ' · '.$vehicle->plate : '' }}</p>
                        </div>
                        @if ($vehicle->isElectric()) <span class="chip bg-emerald-100 text-emerald-800">⚡</span> @endif
                    </div>
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @forelse ($vehicle->reminders->take(3) as $r)
                            <li class="flex items-center justify-between gap-2"><span>{{ $r->icon() }} {{ $r->label }}</span><span class="chip {{ $r->stateClass() }}">{{ $r->dueLabel() }}</span></li>
                        @empty
                            <li class="text-ink-400">Aucun rappel : ouvrez le carnet pour les choisir.</li>
                        @endforelse
                    </ul>
                    @if ($vehicle->logs_sum_cost)
                        <p class="mt-3 text-xs text-ink-500">Dépensé au total : <strong>{{ number_format($vehicle->logs_sum_cost, 0, ',', ' ') }} F</strong></p>
                    @endif
                </a>
            @endforeach
            <details class="card p-5" @if ($errors->any()) open @endif>
                <summary class="cursor-pointer font-semibold text-accent-700">+ Ajouter un véhicule</summary>
                <div class="mt-4">@include('account.logbook._vehicle-form', ['vehicle' => null])</div>
            </details>
        </div>
    @endif
</x-layouts.app>
