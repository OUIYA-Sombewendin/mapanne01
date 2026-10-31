{{-- Boutique de pièces : ce qu'elle vend (le catalogue est affiché juste après). --}}
@php
    $d = $provider->details();
    $vehicles = collect($d?->vehicle_types ?? [])->map(fn ($v) => \App\Enums\VehicleType::options()[$v] ?? $v);
    $modes = array_filter([
        'sells_retail' => '🧍 Vente au détail', 'sells_wholesale' => '📦 Vente en gros',
        'sells_used' => '♻️ Pièces d\'occasion', 'orders_parts' => '📋 Commande les pièces absentes',
    ], fn ($label, $field) => $d?->{$field}, ARRAY_FILTER_USE_BOTH);
@endphp
@if ($d?->exists && ($vehicles->isNotEmpty() || $modes || $d->brands))
    <section class="card p-4 sm:p-5">
        <h2 class="mb-3 font-bold">🛒 La boutique</h2>
        @if ($vehicles->isNotEmpty())
            <p class="text-sm"><span class="font-semibold">Pièces pour :</span> {{ $vehicles->implode(', ') }}</p>
        @endif
        @if ($d->brands) <p class="mt-1 text-sm"><span class="font-semibold">Marques :</span> {{ $d->brands }}</p> @endif
        @if ($modes)
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($modes as $label) <span class="chip bg-ink-50 px-3 py-1.5 text-sm text-ink-700 ring-1 ring-ink-100">{{ $label }}</span> @endforeach
            </div>
        @endif
    </section>
@endif
