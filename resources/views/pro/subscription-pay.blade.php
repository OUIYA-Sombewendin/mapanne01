@php
    use App\Support\Phone;
    $colors = ['orange' => 'from-orange-500 to-orange-600', 'moov' => 'from-sky-600 to-blue-700'];
@endphp
<x-layouts.pro title="Paiement de l'abonnement">
    <div class="mb-4"><x-back :href="route('pro.subscription')" label="Mon abonnement" /></div>

    <div class="mx-auto max-w-3xl">
        <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-6 text-white shadow-lg sm:px-8">
            <div class="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-white/5"></div>
            <p class="text-sm font-semibold tracking-wide text-white/70 uppercase">Offre {{ $subscription->plan->name }}</p>
            <p class="mt-1 text-4xl font-extrabold">{{ number_format($subscription->amount, 0, ',', ' ') }} FCFA</p>
            <p class="mt-1 text-white/80">pour {{ $subscription->plan->period_days }} jours · paiement vérifié par l'équipe {{ setting('site_name') }}</p>
        </section>

        @if (! $operators)
            <div class="card mt-6 p-6 text-center">
                <p class="font-semibold">Le paiement mobile n'est pas encore configuré.</p>
                <p class="mt-1 text-sm text-ink-500">Contactez le support : {{ Phone::format(setting('support_phone')) }} · {{ setting('support_email') }}</p>
            </div>
        @else
            {{-- Étape 1 : payer --}}
            <section class="card mt-6 p-6">
                <h2 class="reg-step"><span>1</span> Envoyez {{ number_format($subscription->amount, 0, ',', ' ') }} F depuis votre téléphone</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($operators as $key => $label)
                        <div class="on-color rounded-2xl bg-gradient-to-br {{ $colors[$key] ?? 'from-accent-600 to-accent-800' }} p-5 text-white shadow-md" x-data="{ copied: false }">
                            <p class="text-sm font-semibold text-white/80">{{ $label }}</p>
                            <p class="mt-1 text-2xl font-extrabold tracking-wide">{{ Phone::format(setting("payment_{$key}_number")) }}</p>
                            @if (filled(setting("payment_{$key}_holder"))) <p class="text-sm text-white/85">au nom de {{ setting("payment_{$key}_holder") }}</p> @endif
                            <button type="button" class="mt-3 rounded-full bg-white/20 px-3 py-1 text-sm font-semibold hover:bg-white/30"
                                    @click="navigator.clipboard?.writeText(@js(preg_replace('/\D/', '', (string) setting("payment_{$key}_number")))); copied = true; setTimeout(() => copied = false, 2000)"
                                    x-text="copied ? 'Numéro copié ✓' : 'Copier le numéro'">Copier le numéro</button>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-sm whitespace-pre-line text-ink-600">{{ setting('subscription_payment_instructions') }}</p>
            </section>

            {{-- Étape 2 : preuve --}}
            <form method="POST" action="{{ route('pro.subscription.proof', $subscription) }}" enctype="multipart/form-data" class="card mt-6 space-y-5 p-6"
                  x-data="{ operator: @js(old('payment_operator', array_key_first($operators))), preview: null }">
                @csrf
                <h2 class="reg-step"><span>2</span> Envoyez la preuve du paiement</h2>

                <div>
                    <p class="field-label">Vous avez payé avec</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($operators as $key => $label)
                            <label class="cursor-pointer rounded-full border px-4 py-2 text-sm font-semibold transition"
                                   :class="operator === @js($key) ? 'border-accent-600 bg-accent-600 text-white' : 'border-ink-200 bg-white'">
                                <input type="radio" name="payment_operator" value="{{ $key }}" x-model="operator" class="sr-only"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('payment_operator') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="payer_phone" type="tel" label="Numéro utilisé pour payer" :value="$subscription->payer_phone ? Phone::format($subscription->payer_phone) : Phone::format(auth()->user()->provider->phone)" inputmode="tel" placeholder="70 12 34 56" required />
                    <x-field name="payment_reference" label="Référence de la transaction" :value="$subscription->payment_reference" placeholder="Ex. PP231101.1234.A56789" hint="Elle figure dans le SMS de confirmation." required />
                </div>

                <div>
                    <label for="proof" class="field-label">Capture d'écran du message de confirmation</label>
                    <label for="proof" class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-ink-200 p-6 text-center transition hover:border-accent-400 hover:bg-accent-50/50">
                        <template x-if="preview"><img :src="preview" alt="Aperçu de la capture" class="max-h-64 rounded-xl shadow"></template>
                        <template x-if="!preview"><span class="text-4xl">📸</span></template>
                        <span class="text-sm font-semibold text-accent-700" x-text="preview ? 'Changer la capture' : 'Choisir la capture'">Choisir la capture</span>
                        <span class="text-xs text-ink-400">JPG, PNG ou WebP, 5 Mo max.</span>
                    </label>
                    <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" required
                           @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null">
                    @error('proof') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                @if ($subscription->hasProof())
                    <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Une preuve a déjà été envoyée le {{ $subscription->proof_submitted_at->format('d/m/Y à H:i') }}. En envoyer une nouvelle la remplace.</p>
                @endif

                <button class="btn btn-primary w-full py-3 text-base">Envoyer la preuve de paiement</button>
                <p class="text-center text-xs text-ink-500">La capture n'est visible que par l'équipe {{ setting('site_name') }}. Une même référence ne peut servir qu'une fois.</p>
            </form>
        @endif
    </div>
</x-layouts.pro>
