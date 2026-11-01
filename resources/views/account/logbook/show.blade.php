@php
    $money = fn ($v) => number_format((int) $v, 0, ',', ' ').' F';
@endphp
<x-layouts.app :title="'Carnet · '.$vehicle->title()">
    {{-- En-tête du véhicule --}}
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <span class="flex size-16 items-center justify-center rounded-2xl bg-ink-50 text-4xl">{{ $vehicle->icon() }}</span>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold tracking-widest text-accent-700 uppercase">Carnet d'entretien</p>
            <h1 class="truncate text-2xl font-extrabold">{{ $vehicle->title() }}</h1>
            <p class="text-sm text-ink-500">{{ $vehicle->type->label() }} · {{ $vehicle->energyLabel() }}{{ $vehicle->plate ? ' · '.$vehicle->plate : '' }}</p>
        </div>
        @if ($vehicles->count() > 1)
            <select class="input w-auto" aria-label="Changer de véhicule" onchange="location = this.value">
                @foreach ($vehicles as $v)
                    <option value="{{ route('logbook.show', $v) }}" @selected($v->id === $vehicle->id)>{{ $v->title() }}</option>
                @endforeach
            </select>
        @endif
        <a href="{{ route('logbook.index') }}" class="btn btn-ghost btn-sm">Tous mes véhicules</a>
    </div>

    @if ($vehicle->isElectric() && feature('electric_page_enabled'))
        <a href="{{ route('electric') }}" class="mb-5 flex items-center gap-3 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900 ring-1 ring-emerald-200 hover:bg-emerald-100">
            <span class="text-2xl">⚡</span>
            <span class="flex-1"><strong>Véhicule électrique :</strong> trouvez les réparateurs spécialisés et les points de recharge ou d'échange de batterie.</span>
            <span>→</span>
        </a>
    @endif

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <div class="space-y-5">
            {{-- Prochains entretiens --}}
            <section class="card p-5">
                <h2 class="mb-3 font-bold">⏰ Prochains entretiens</h2>
                <ul class="divide-y divide-ink-50">
                    @forelse ($vehicle->reminders as $r)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <div>
                                <p class="font-semibold">{{ $r->icon() }} {{ $r->label }}</p>
                                <p class="text-xs text-ink-400">Tous les {{ $r->every_months }} mois{{ $r->last_done_on ? ' · fait le '.$r->last_done_on->format('d/m/Y') : '' }}</p>
                            </div>
                            <span class="chip shrink-0 {{ $r->stateClass() }}">{{ $r->dueLabel() }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-ink-500">Aucun rappel pour l'instant. Choisissez-les ci-dessous : on vous prévient {{ \App\Models\VehicleReminder::NOTICE_DAYS }} jours avant.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Choix des rappels --}}
            <details class="card p-5" @if ($vehicle->reminders->isEmpty() || $errors->has('tasks.*')) open @endif>
                <summary class="cursor-pointer font-bold">🔔 Choisir mes rappels</summary>
                <p class="mt-1 mb-3 text-sm text-ink-500">Cochez ce que vous voulez suivre. La date du dernier entretien est facultative : sans elle, le premier rappel arrive au bout d'une période.</p>
                <form method="POST" action="{{ route('logbook.reminders', $vehicle) }}">
                    @csrf @method('PUT')
                    <div class="space-y-2">
                        @foreach ($tasks as $key => $task)
                            @php($r = $reminders->get($key))
                            <div x-data="{ on: @js((bool) old("tasks.$key.on", $r ? true : ($vehicle->reminders->isEmpty() && $task['suggested']))) }"
                                 class="rounded-xl border border-ink-100 p-3" :class="on && 'border-accent-300 bg-accent-50/40'">
                                <label class="flex cursor-pointer items-center gap-3">
                                    <input type="hidden" name="tasks[{{ $key }}][on]" value="0">
                                    <input type="checkbox" name="tasks[{{ $key }}][on]" value="1" x-model="on" class="size-5 rounded">
                                    <span class="flex-1 font-semibold">{{ $task['icon'] }} {{ $task['label'] }}</span>
                                    <span class="text-xs text-ink-400">conseillé : {{ $task['every'] }} mois</span>
                                </label>
                                <div x-show="on" x-cloak class="mt-2 grid grid-cols-2 gap-2 pl-8">
                                    <label class="text-xs text-ink-500">Tous les
                                        <select name="tasks[{{ $key }}][every]" class="input-sm mt-0.5 w-full">
                                            @foreach ([1, 2, 3, 4, 6, 12, 24] as $m)
                                                <option value="{{ $m }}" @selected((int) old("tasks.$key.every", $r?->every_months ?? $task['every']) === $m)>{{ $m }} mois</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="text-xs text-ink-500">{{ $key === 'assurance' ? 'Souscrite le' : 'Dernière fois le' }}
                                        <input type="date" name="tasks[{{ $key }}][last]" value="{{ old("tasks.$key.last", $r?->last_done_on?->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" class="input-sm mt-0.5 w-full">
                                    </label>
                                </div>
                                @error("tasks.$key.last") <p class="field-error pl-8">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-primary mt-3 w-full">Enregistrer mes rappels</button>
                </form>
            </details>

            <details class="card p-5">
                <summary class="cursor-pointer font-bold">✏️ Modifier le véhicule</summary>
                <div class="mt-4">@include('account.logbook._vehicle-form', ['vehicle' => $vehicle])</div>
                <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}" class="mt-3 text-right" onsubmit="return confirm('Supprimer ce véhicule et tout son carnet ?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-red-600">Supprimer ce véhicule</button>
                </form>
            </details>
        </div>

        <div class="space-y-5">
            {{-- Ajout rapide --}}
            <section class="card p-5" x-data="{ task: @js(old('task', '')) }">
                <h2 class="mb-3 font-bold">➕ J'ai fait un entretien</h2>
                <form method="POST" action="{{ route('logbook.logs.store', $vehicle) }}" class="space-y-3">
                    @csrf
                    <div class="flex flex-wrap gap-2">
                        @foreach ($tasks as $key => $task)
                            <label class="chip chip-filter cursor-pointer px-3 py-2 text-sm">
                                <input type="radio" name="task" value="{{ $key }}" x-model="task" class="sr-only">{{ $task['icon'] }} {{ $task['label'] }}
                            </label>
                        @endforeach
                        <label class="chip chip-filter cursor-pointer px-3 py-2 text-sm">
                            <input type="radio" name="task" value="" x-model="task" class="sr-only">🔧 Autre réparation
                        </label>
                    </div>
                    <div x-show="task === ''">
                        <x-field name="label" label="Qu'est-ce qui a été fait ?" placeholder="Ex. changement d'embrayage" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="done_on" type="date" label="Date" :value="today()->format('Y-m-d')" max="{{ today()->format('Y-m-d') }}" required />
                        <x-field name="cost" type="text" inputmode="numeric" label="Prix payé (F)" placeholder="Facultatif" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($providers->isNotEmpty())
                            <div>
                                <label class="field-label" for="provider_id">Chez qui ?</label>
                                <select id="provider_id" name="provider_id" class="input">
                                    <option value="">— Autre —</option>
                                    @foreach ($providers as $p)
                                        <option value="{{ $p->id }}" @selected(old('provider_id') == $p->id)>{{ $p->type->icon() }} {{ $p->business_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <x-field name="provider_name" :label="$providers->isNotEmpty() ? 'Ou nom du prestataire' : 'Chez qui ? (facultatif)'" placeholder="Ex. Garage du quartier" />
                    </div>
                    <x-field name="notes" label="Note (facultatif)" placeholder="Ex. huile 10W40, prochaine fois changer le filtre" />
                    @error('task') <p class="field-error">{{ $message }}</p> @enderror
                    <button class="btn btn-primary w-full">Ajouter au carnet</button>
                </form>
            </section>

            {{-- Historique --}}
            <section class="card p-5">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <h2 class="font-bold">📒 Historique</h2>
                    @if ($spent['total'])
                        <p class="text-sm text-ink-500">Cette année : <strong>{{ $money($spent['year']) }}</strong> · Total : <strong>{{ $money($spent['total']) }}</strong></p>
                    @endif
                </div>
                <ul class="divide-y divide-ink-50">
                    @forelse ($vehicle->logs as $log)
                        <li class="flex items-start gap-3 py-2.5">
                            <span class="text-xl">{{ $log->icon() }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ $log->label }}</p>
                                <p class="text-xs text-ink-500">
                                    {{ $log->done_on->format('d/m/Y') }}
                                    @if ($log->provider && $log->provider->slug)
                                        · <a href="{{ route('providers.show', $log->provider) }}" class="text-accent-700 hover:underline">{{ $log->provider->business_name }}</a>
                                    @elseif ($log->providerLabel())
                                        · {{ $log->providerLabel() }}
                                    @endif
                                </p>
                                @if ($log->notes) <p class="mt-0.5 text-xs text-ink-400">{{ $log->notes }}</p> @endif
                            </div>
                            @if ($log->cost) <span class="text-sm font-semibold whitespace-nowrap">{{ $money($log->cost) }}</span> @endif
                            <form method="POST" action="{{ route('logbook.logs.destroy', [$vehicle, $log]) }}" onsubmit="return confirm('Supprimer cet entretien ?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-ink-400 hover:text-red-600" aria-label="Supprimer">✕</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-ink-400">Aucun entretien noté. Le prochain prend 10 secondes.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-layouts.app>
