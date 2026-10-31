{{-- Station-service : d'abord les carburants disponibles, puis les services sur place. --}}
@php
    $d = $provider->details();
    $fuelLabels = \App\Support\ProviderProfiles::kinds()['station']['fields']['fuel_types']['options'];
    $services = array_filter([
        'open_24h' => '🕐 Ouverte 24 h/24', 'has_shop' => '🛒 Boutique', 'has_car_wash' => '🧽 Lavage',
        'has_tire_air' => '🛞 Gonflage', 'has_lubricants' => '🛢️ Lubrifiants', 'has_mechanic' => '🔧 Mécanicien sur place',
    ], fn ($label, $field) => $d?->{$field}, ARRAY_FILTER_USE_BOTH);
@endphp
@if ($d?->exists)
    <section class="card p-4 sm:p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-bold">⛽ Carburants</h2>
            @if ($d->brand) <span class="chip bg-ink-800 text-white">{{ $d->brand }}</span> @endif
        </div>
        @if (! empty($d->fuel_types))
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($d->fuel_types as $fuel)
                    <div class="rounded-xl bg-emerald-50 px-3 py-3 text-center ring-1 ring-emerald-100">
                        <p class="font-bold text-emerald-900">{{ $fuelLabels[$fuel] ?? $fuel }}</p>
                        <p class="text-xs text-emerald-700">disponible</p>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-ink-400">Carburants non précisés par la station.</p>
        @endif
        @if ($services)
            <h3 class="mt-4 mb-2 text-sm font-semibold text-ink-700">Sur place</h3>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($services as $label) <span class="chip bg-ink-50 px-3 py-1.5 text-sm text-ink-700 ring-1 ring-ink-100">{{ $label }}</span> @endforeach
            </div>
        @endif
    </section>
@endif
