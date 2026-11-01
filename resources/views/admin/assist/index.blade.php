<x-layouts.admin title="Dépannages">
    <h1 class="mb-1 text-2xl font-extrabold">Dépannage en direct</h1>
    <p class="mb-5 text-sm text-ink-500">Demandes envoyées par les conducteurs en panne, et réponse des prestataires. Réglages : Configuration › Dépannage en direct.</p>

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
        @foreach ([
            ['flag', $stats['active'], 'En cours'],
            ['chart', $stats['total'], 'Demandes (30 j)'],
            ['shield', $stats['done'], 'Terminées (30 j)'],
            ['clock', $stats['expired'], 'Sans réponse (30 j)'],
            ['phone', $stats['minutes'] !== null ? round($stats['minutes']).' min' : '—', 'Délai moyen d\'acceptation'],
        ] as [$icon, $value, $label])
            <div class="card border-t-4 border-accent-600 p-4 text-center">
                <span class="mx-auto flex size-11 items-center justify-center rounded-xl bg-accent-50 text-accent-700"><x-icon :name="$icon" class="size-6" /></span>
                <p class="mt-2 text-2xl font-extrabold">{{ $value }}</p>
                <p class="text-sm font-semibold">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <x-filter-bar :keys="['q', 'status', 'problem', 'vehicle', 'energy', 'city']">
        <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-52" placeholder="Nom ou téléphone">
        <select name="status" class="input-sm">
            <option value="">Tous les statuts</option>
            <option value="active" @selected(request('status') === 'active')>En cours</option>
            @foreach (\App\Models\AssistRequest::STATUSES as $key => [$label]) <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option> @endforeach
        </select>
        <select name="problem" class="input-sm">
            <option value="">Tous les problèmes</option>
            @foreach ($problems as $key => $p) <option value="{{ $key }}" @selected(request('problem') === $key)>{{ $p['icon'] }} {{ $p['label'] }}</option> @endforeach
        </select>
        <select name="vehicle" class="input-sm">
            <option value="">Tous les véhicules</option>
            @foreach (\App\Enums\VehicleType::options() as $key => $label) <option value="{{ $key }}" @selected(request('vehicle') === $key)>{{ $label }}</option> @endforeach
        </select>
        <select name="energy" class="input-sm">
            <option value="">Thermique et électrique</option>
            @foreach (\App\Support\AssistProblems::energies() as $key => $label) <option value="{{ $key }}" @selected(request('energy') === $key)>{{ $label }}</option> @endforeach
        </select>
        <select name="city" class="input-sm">
            <option value="">Toutes les villes</option>
            @foreach ($cities as $c) <option value="{{ $c->id }}" @selected(request('city') == $c->id)>{{ $c->name }}</option> @endforeach
        </select>
    </x-filter-bar>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr>
                <x-sort-th column="created" label="Date" />
                <x-sort-th column="problem" label="Problème" />
                <th>Conducteur</th>
                <x-sort-th column="notified" label="Prévenus" align="center" />
                <th>Prestataire</th>
                <x-sort-th column="status" label="Statut" />
                <th></th>
            </tr></thead>
            <tbody>
                @forelse ($requests as $a)
                    <tr>
                        <td class="whitespace-nowrap text-ink-500">{{ $a->created_at->format('d/m H:i') }}</td>
                        <td>{{ $a->problemIcon() }} {{ $a->problemLabel() }}<br><span class="text-xs text-ink-400">{{ $a->vehicleLabel() }}{{ $a->city ? ' · '.$a->city->name : '' }}</span></td>
                        <td>{{ $a->contact_name }}<br><span class="text-xs text-ink-400">{{ \App\Support\Phone::format($a->contact_phone) }}</span></td>
                        <td class="text-center">{{ $a->notified_count }}</td>
                        <td>@if ($a->provider) <a href="{{ route('admin.providers.show', $a->provider_id) }}" class="text-accent-700 hover:underline">{{ $a->provider->business_name }}</a> @if ($a->accepted_at) <br><span class="text-xs text-ink-400">en {{ max(1, (int) $a->created_at->diffInMinutes($a->accepted_at)) }} min</span> @endif @else — @endif</td>
                        <td><span class="chip {{ $a->statusClass() }}">{{ $a->statusLabel() }}</span>
                            @if ($a->close_reason) <span class="block text-xs text-ink-400">{{ \Illuminate\Support\Str::limit($a->close_reason, 60) }}</span> @endif</td>
                        <td class="whitespace-nowrap">
                            <a href="{{ $a->mapUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Carte</a>
                            @if ($a->isActive())
                                @can('providers.manage')
                                    <form method="POST" action="{{ route('admin.assist.close', $a) }}" class="inline" x-data @submit="const r = prompt('Motif de la fermeture ?'); if (!r) { $event.preventDefault(); return; } $el.querySelector('[name=reason]').value = r">
                                        @csrf <input type="hidden" name="reason">
                                        <button class="btn btn-ghost btn-sm text-red-700">Fermer</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-ink-400">Aucune demande.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.admin>
