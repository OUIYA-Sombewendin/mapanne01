{{-- Dépanneur : tarifs, zone et flotte en premier. Un client en panne doit pouvoir décider et appeler vite. --}}
@php
    $d = $provider->details();
    $f = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $vehicles = collect($d?->vehicle_types ?? [])->map(fn ($v) => \App\Enums\VehicleType::options()[$v] ?? $v)->implode(', ');
@endphp
<section class="card overflow-hidden">
    <div class="on-color bg-gradient-to-br from-red-600 to-red-700 p-4 text-white sm:p-5">
        <p class="text-sm text-red-100">Dépannage et remorquage{{ $d?->available_24h ? ' · 24 h/24' : '' }}</p>
        <a href="{{ $provider->callUrl() }}" class="mt-2 flex items-center justify-center gap-2 rounded-xl bg-white py-3 text-lg font-extrabold text-red-700 shadow">
            🚨 Appeler le dépanneur · {{ \App\Support\Phone::format($provider->phone) }}
        </a>
    </div>
    @if ($d?->exists)
        <div class="grid grid-cols-2 gap-px bg-ink-100 sm:grid-cols-4">
            @foreach ([
                ['Prise en charge', $d->base_price ? $f($d->base_price).' F' : '—'],
                ['Prix au km', $d->price_per_km ? $f($d->price_per_km).' F' : '—'],
                ['Zone couverte', $d->coverage_km ? $d->coverage_km.' km' : '—'],
                ['Flotte', $d->trucks_count ? $d->trucks_count.' dépanneuse(s)' : '—'],
            ] as [$label, $value])
                <div class="bg-white p-3 text-center">
                    <p class="text-lg font-extrabold">{{ $value }}</p>
                    <p class="text-xs text-ink-500">{{ $label }}</p>
                </div>
            @endforeach
        </div>
        @if ($vehicles || $d->max_weight_tons)
            <p class="px-4 py-3 text-sm text-ink-600 sm:px-5">🚛 Remorque : {{ $vehicles ?: 'tous véhicules' }}@if ($d->max_weight_tons) · jusqu'à {{ number_format($d->max_weight_tons, 1, ',', ' ') }} t @endif</p>
        @endif
        <p class="px-4 pb-3 text-xs text-ink-400 sm:px-5">Tarifs indicatifs annoncés par le dépanneur : faites confirmer le prix au téléphone.</p>
    @endif
</section>
