{{-- Garage : capacité, équipements et spécialités de l'atelier. --}}
@php
    $d = $provider->details();
    $specialties = collect($d?->specialties ?? [])->map(fn ($s) => \App\Support\ProviderProfiles::mechanicSpecialties()[$s] ?? $s);
    $equipment = array_filter([
        'has_diagnostic' => ['💻', 'Diagnostic électronique'], 'has_lift' => ['🏗️', 'Pont élévateur'],
        'has_paint_shop' => ['🎨', 'Tôlerie et peinture'], 'accepts_insurance' => ['📄', 'Travaille avec les assurances'],
    ], fn ($v, $field) => $d?->{$field}, ARRAY_FILTER_USE_BOTH);
@endphp
@if ($d?->exists)
    <section class="card p-4 sm:p-5">
        <h2 class="mb-3 font-bold">🏠 L'atelier</h2>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @if ($d->capacity) <div class="rounded-xl bg-ink-50 p-3 text-center"><p class="text-xl font-extrabold">{{ $d->capacity }}</p><p class="text-xs text-ink-500">places</p></div> @endif
            @if ($d->employees) <div class="rounded-xl bg-ink-50 p-3 text-center"><p class="text-xl font-extrabold">{{ $d->employees }}</p><p class="text-xs text-ink-500">mécaniciens</p></div> @endif
            @foreach ($equipment as [$icon, $label])
                <div class="rounded-xl bg-emerald-50 p-3 text-center ring-1 ring-emerald-100"><p class="text-xl">{{ $icon }}</p><p class="text-xs font-medium text-emerald-900">{{ $label }}</p></div>
            @endforeach
        </div>
        @if ($specialties->isNotEmpty() || $d->brands)
            <div class="mt-4 space-y-1 text-sm text-ink-600">
                @if ($specialties->isNotEmpty()) <p><span class="font-semibold text-ink-800">Spécialités :</span> {{ $specialties->implode(', ') }}</p> @endif
                @if ($d->brands) <p><span class="font-semibold text-ink-800">Marques :</span> {{ $d->brands }}</p> @endif
            </div>
        @endif
    </section>
@endif
