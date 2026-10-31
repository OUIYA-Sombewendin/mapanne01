{{-- Paiement (et livraison pour les vendeurs de pièces). Paramètres : $id, $title, $text. --}}
<section id="{{ $id ?? 'paiement' }}" class="card scroll-mt-28 p-6">
    @include('pro._section-head', ['icon' => 'card', 'title' => $title ?? 'Paiement', 'text' => $text ?? 'Moyens de paiement acceptés, affichés sur votre fiche.'])
    <div class="flex flex-wrap gap-2">
        @foreach ($paymentMethods as $method)
            <label class="chip chip-filter px-3 py-1.5 text-sm">
                <input type="checkbox" name="payment_methods[]" value="{{ $method }}" class="sr-only" @checked(in_array($method, old('payment_methods', (array) $provider->payment_methods)))>{{ $method }}
            </label>
        @endforeach
    </div>
    @if (feature('delivery_enabled') && $provider->canSellParts())
        <div class="mt-4 space-y-2 rounded-xl bg-ink-50 p-4" x-data="{ delivery: {{ old('delivery_available', $provider->delivery_available) ? 'true' : 'false' }} }">
            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="delivery_available" value="1" x-model="delivery" class="checkbox"> 🛵 Je livre les pièces
            </label>
            <div x-show="delivery" x-cloak>
                <x-field name="delivery_details" label="Zones, délais et frais" :value="$provider->delivery_details" placeholder="Ex. Ouaga centre en 2 h, 1 000 FCFA" />
            </div>
        </div>
    @endif
</section>
