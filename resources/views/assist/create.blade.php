@php
    $vehicleIcons = ['moto' => '🛵', 'voiture' => '🚗', 'tricycle' => '🛺', 'camion' => '🚚'];
    $me = auth()->user();
@endphp
<x-layouts.app title="Je suis en panne">
    <div class="mx-auto max-w-2xl">
        <section class="on-color relative overflow-hidden rounded-3xl bg-gradient-to-br from-red-600 to-brand-600 px-6 py-6 text-white shadow-lg">
            <span class="pointer-events-none absolute -right-10 -bottom-14 size-44 rounded-full bg-white/10"></span>
            <h1 class="relative text-2xl font-extrabold sm:text-3xl">🆘 Je suis en panne</h1>
            <p class="relative mt-1 text-white/90">Indiquez votre problème et votre position : les prestataires les plus proches sont prévenus tout de suite.</p>
            <a href="{{ route('emergency') }}" class="relative mt-3 inline-block rounded-full bg-white/15 px-3 py-1 text-sm font-semibold hover:bg-white/25">Un blessé ? Appelez d'abord les secours →</a>
        </section>

        <form method="POST" action="{{ route('assist.store') }}" class="mt-5 space-y-6"
              x-data="assistForm(@js(['vehicle' => old('vehicle_type', request('vehicle')), 'energy' => old('energy', request('energy')), 'problem' => old('problem', request('problem')), 'lat' => old('latitude'), 'lng' => old('longitude')]))">
            @csrf

            {{-- 1. Véhicule --}}
            <section class="card p-5">
                <h2 class="reg-step"><span>1</span> Votre véhicule</h2>
                <div class="grid grid-cols-4 gap-2">
                    @foreach ($vehicles as $value => $label)
                        <label class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border px-1 py-3 text-center transition"
                               :class="vehicle === @js($value) ? 'border-accent-500 bg-accent-50 ring-2 ring-accent-500' : 'border-ink-200'">
                            <input type="radio" name="vehicle_type" value="{{ $value }}" x-model="vehicle" class="sr-only">
                            <span class="text-3xl">{{ $vehicleIcons[$value] ?? '🚗' }}</span>
                            <span class="text-sm font-bold">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($energies as $value => $label)
                        <label class="cursor-pointer rounded-full border px-4 py-1.5 text-sm font-semibold transition"
                               :class="energy === @js($value) ? 'border-accent-600 bg-accent-600 text-white' : 'border-ink-200 bg-white'">
                            <input type="radio" name="energy" value="{{ $value }}" x-model="energy" class="sr-only">
                            {{ $value === 'electrique' ? '⚡ ' : '⛽ ' }}{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('vehicle_type') <p class="field-error">{{ $message }}</p> @enderror
            </section>

            {{-- 2. Problème --}}
            <section class="card p-5">
                <h2 class="reg-step"><span>2</span> Le problème</h2>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($problems as $value => $problem)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-2xl border px-3 py-3 transition"
                               :class="problem === @js($value) ? 'border-accent-500 bg-accent-50 ring-2 ring-accent-500' : 'border-ink-200'"
                               @if (! empty($problem['electric'])) x-show="energy === 'electrique'" @endif
                               @if (! empty($problem['fuel'])) x-show="energy !== 'electrique'" @endif
                               @if ($value === 'transport_moto') x-show="vehicle === 'moto' || vehicle === 'tricycle'" @endif>
                            <input type="radio" name="problem" value="{{ $value }}" x-model="problem" class="sr-only" required>
                            <span class="text-2xl">{{ $problem['icon'] }}</span>
                            <span class="text-sm leading-tight font-semibold">{{ $problem['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('problem') <p class="field-error">{{ $message }}</p> @enderror
                <div class="mt-4">
                    <x-field name="description" label="Précisions (facultatif)" placeholder="Ex. la moto cale quand j'accélère, pneu arrière crevé…" maxlength="500" />
                </div>
            </section>

            {{-- 3. Position --}}
            <section class="card p-5">
                <h2 class="reg-step"><span>3</span> Où êtes-vous ?</h2>
                <input type="hidden" name="latitude" :value="lat">
                <input type="hidden" name="longitude" :value="lng">

                <div class="flex items-center gap-3 rounded-2xl px-4 py-3"
                     :class="lat ? 'bg-emerald-50 text-emerald-800' : (locating ? 'bg-accent-50 text-accent-800' : 'bg-amber-50 text-amber-800')">
                    <span class="text-2xl" x-text="lat ? '📍' : (locating ? '⏳' : '⚠️')"></span>
                    <p class="flex-1 text-sm font-semibold" x-text="lat ? 'Position trouvée. Les prestataires verront à quelle distance vous êtes.' : (locating ? 'Recherche de votre position…' : (error || 'Position non trouvée.'))"></p>
                    <button type="button" class="btn btn-ghost btn-sm" @click="locate" x-show="!locating" x-text="lat ? 'Actualiser' : 'Réessayer'"></button>
                </div>

                <div x-show="!lat && !locating" x-cloak class="mt-4 space-y-2">
                    <p class="text-sm text-ink-600">Sans position, choisissez votre ville et votre quartier :</p>
                    <x-location-select :cities="$cities" />
                </div>
                @error('latitude') <p class="field-error">{{ $message }}</p> @enderror

                <div class="mt-4">
                    <x-field name="landmark" label="Repère pour vous trouver (facultatif)" placeholder="Ex. devant la pharmacie du rond-point, station Total…" maxlength="200" />
                </div>
            </section>

            {{-- 4. Contact --}}
            <section class="card p-5">
                <h2 class="reg-step"><span>4</span> Pour vous joindre</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="contact_name" label="Votre nom" :value="$me?->name" autocomplete="name" required />
                    <x-field name="contact_phone" type="tel" label="Votre téléphone" :value="$me?->phone ? \App\Support\Phone::format($me->phone) : null" inputmode="tel" autocomplete="tel" placeholder="70 12 34 56" required />
                </div>
                <p class="mt-2 text-xs text-ink-500">Votre numéro n'est donné qu'au prestataire qui accepte de venir.</p>
            </section>

            <button class="btn w-full bg-red-600 py-4 text-lg text-white shadow-lg hover:bg-red-700">🆘 Envoyer ma demande</button>
            <p class="text-center text-sm text-ink-500">Vous suivrez la réponse sur la page suivante. Gardez votre téléphone allumé.</p>
        </form>
    </div>
</x-layouts.app>
