{{-- Mécanicien : déplacement, expérience, spécialités. --}}
@php
    $d = $provider->details();
    $specialties = collect($d?->specialties ?? [])->map(fn ($s) => \App\Support\ProviderProfiles::mechanicSpecialties()[$s] ?? $s);
    $vehicles = collect($d?->vehicle_types ?? [])->map(fn ($v) => \App\Enums\VehicleType::options()[$v] ?? $v);
@endphp
@if ($d?->exists)
    <section class="card p-4 sm:p-5">
        <h2 class="mb-3 font-bold">🔧 Le mécanicien</h2>
        <div class="grid gap-2 sm:grid-cols-3">
            <div @class(['rounded-xl p-3', 'bg-emerald-50 ring-1 ring-emerald-100' => $d->is_mobile, 'bg-ink-50' => ! $d->is_mobile])>
                <p class="font-bold">{{ $d->is_mobile ? '🛵 Se déplace' : '🏠 À l\'atelier' }}</p>
                <p class="text-xs text-ink-500">{{ $d->is_mobile ? ($d->travel_radius_km ? 'jusqu\'à '.$d->travel_radius_km.' km' : 'chez le client') : 'Venez avec votre véhicule' }}</p>
            </div>
            @if ($d->years_experience)
                <div class="rounded-xl bg-ink-50 p-3"><p class="font-bold">{{ $d->years_experience }} ans</p><p class="text-xs text-ink-500">d'expérience</p></div>
            @endif
            @if ($vehicles->isNotEmpty())
                <div class="rounded-xl bg-ink-50 p-3"><p class="font-bold">{{ $vehicles->implode(', ') }}</p><p class="text-xs text-ink-500">véhicules réparés</p></div>
            @endif
        </div>
        @if ($specialties->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($specialties as $s) <span class="chip bg-brand-50 px-3 py-1.5 text-sm text-brand-800">{{ $s }}</span> @endforeach
            </div>
        @endif
        @if ($d->brands) <p class="mt-3 text-sm text-ink-600"><span class="font-semibold text-ink-800">Marques :</span> {{ $d->brands }}</p> @endif
    </section>
@endif
