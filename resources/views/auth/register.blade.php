@php
    use App\Support\ProviderProfiles;

    // Textes de chaque métier pour le panneau latéral et les libellés (changent sans recharger la page).
    $tradeCopy = $trades->mapWithKeys(fn ($t) => [$t->slug => [
        'pitch' => ProviderProfiles::copy($t->profileKind())['pitch'],
        'name' => ProviderProfiles::copy($t->profileKind())['name'],
        'hint' => ProviderProfiles::copy($t->profileKind())['name_hint'],
        'phone' => ProviderProfiles::copy($t->profileKind())['phone'],
    ]]);
    $clientPitch = 'Trouvez vite un mécanicien, un dépanneur, une station ou une pièce près de vous.';
@endphp
<x-layouts.app title="Créer un compte">
    <div class="mx-auto max-w-5xl" x-data="{
        type: @js(old('account_type', $accountType)),
        trade: @js(old('provider_type', '')),
        copy: @js($tradeCopy),
        get current() { return this.copy[this.trade] ?? null },
    }">
        <div class="grid overflow-hidden rounded-3xl bg-white shadow-lg ring-1 ring-ink-100 lg:grid-cols-[340px_1fr]">
            {{-- Panneau coloré --}}
            <aside class="admin-hero on-color relative overflow-hidden p-6 text-white sm:p-9">
                <span class="pointer-events-none absolute -right-16 -bottom-20 size-64 rounded-full bg-white/10"></span>
                <span class="pointer-events-none absolute -top-10 -left-10 size-40 rounded-full bg-white/5"></span>
                <div class="relative">
                    <p class="text-sm font-semibold tracking-wide text-white/70 uppercase">{{ setting('site_name') }}</p>
                    <h1 class="mt-2 text-3xl font-extrabold" x-text="type === 'pro' ? 'Inscrivez votre activité' : 'Créez votre compte'">Créer un compte</h1>
                    <span class="mt-3 block h-1 w-20 rounded-full bg-brand-500"></span>
                    <p class="mt-5 text-lg text-white/90" x-text="type === 'pro' ? (current?.pitch ?? 'Choisissez votre métier : le formulaire s\'adapte à votre activité.') : @js($clientPitch)"></p>

                    <div class="mt-7 hidden text-white/90 lg:block">
                        <template x-if="type === 'pro'">
                            <div class="space-y-3">
                                <p class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm">✓</span> Inscription gratuite</p>
                                <p class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm">✓</span> Fiche vérifiée par notre équipe avant publication</p>
                                <p class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm">✓</span> Les clients vous appellent ou vous écrivent sur WhatsApp</p>
                            </div>
                        </template>
                        <template x-if="type !== 'pro'">
                            <div class="space-y-3">
                                <p class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm">✓</span> Gratuit, en moins d'une minute</p>
                                <p class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm">✓</span> Des professionnels vérifiés près de vous</p>
                                <p class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm">✓</span> Avis, favoris et demandes de pièces</p>
                            </div>
                        </template>
                    </div>

                    <p class="mt-6 text-sm text-white/75 lg:mt-10">Déjà inscrit ? <a href="{{ route('login') }}" class="font-bold text-white underline">Se connecter</a></p>
                </div>
            </aside>

            {{-- Parcours --}}
            <form method="POST" action="{{ route('register') }}" class="space-y-7 p-6 sm:p-9">
                @csrf
                <input type="hidden" name="account_type" :value="type">

                {{-- Étape 1 : profil --}}
                @if ($clientsOpen && $prosOpen)
                    <section>
                        <h2 class="reg-step"><span>1</span> Vous êtes…</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <button type="button" @click="type = 'client'" :class="type === 'client' ? 'border-accent-500 bg-accent-50 ring-2 ring-accent-500' : 'border-ink-200 hover:border-accent-300'" class="flex items-center gap-3 rounded-2xl border p-4 text-left transition">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white text-2xl shadow-sm">🚗</span>
                                <span><span class="block font-bold">Je cherche de l'aide</span><span class="block text-sm text-ink-500">Automobiliste, motocycliste</span></span>
                            </button>
                            <button type="button" @click="type = 'pro'" :class="type === 'pro' ? 'border-accent-500 bg-accent-50 ring-2 ring-accent-500' : 'border-ink-200 hover:border-accent-300'" class="flex items-center gap-3 rounded-2xl border p-4 text-left transition">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white text-2xl shadow-sm">🔧</span>
                                <span><span class="block font-bold">Je suis un professionnel</span><span class="block text-sm text-ink-500">Mécanicien, garage, station, pièces…</span></span>
                            </button>
                        </div>
                        @error('account_type') <p class="field-error">{{ $message }}</p> @enderror
                    </section>
                @endif

                {{-- Étape 2 : métier (pros) --}}
                <template x-if="type === 'pro'">
                    <section>
                        <h2 class="reg-step"><span>{{ $clientsOpen && $prosOpen ? 2 : 1 }}</span> Votre métier</h2>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 {{ $trades->count() >= 5 ? 'xl:grid-cols-5' : ($trades->count() === 4 ? 'xl:grid-cols-4' : '') }}">
                            @foreach ($trades as $t)
                                <label :class="trade === @js($t->slug) ? 'border-accent-500 bg-accent-50 ring-2 ring-accent-500 text-accent-800' : 'border-ink-200 hover:border-accent-300'" class="flex cursor-pointer flex-col items-center gap-1.5 rounded-2xl border px-2 py-4 text-center transition">
                                    <input type="radio" name="provider_type" value="{{ $t->slug }}" x-model="trade" class="sr-only" required>
                                    <span class="text-3xl">{{ $t->icon() }}</span>
                                    <span class="text-sm leading-tight font-bold">{{ $t->shortLabel() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('provider_type') <p class="field-error">{{ $message }}</p> @enderror
                    </section>
                </template>

                {{-- Étape 3 : informations --}}
                <section x-show="type !== 'pro' || trade" x-cloak>
                    <h2 class="reg-step"><span x-text="type === 'pro' ? @js($clientsOpen && $prosOpen ? 3 : 2) : @js($clientsOpen && $prosOpen ? 2 : 1)"></span> Vos informations</h2>

                    <div class="space-y-4">
                        <template x-if="type === 'pro'">
                            <div class="space-y-4 rounded-2xl border border-accent-100 bg-accent-50/50 p-5">
                                <div>
                                    <label for="business_name" class="field-label" x-text="current?.name ?? 'Nom de l\'activité'"></label>
                                    <input id="business_name" name="business_name" value="{{ old('business_name') }}" :placeholder="current?.hint" required @class(['input', 'input-error' => $errors->has('business_name')])>
                                    @error('business_name') <p class="field-error">{{ $message }}</p> @enderror
                                </div>

                                {{-- Questions propres à chaque métier --}}
                                @foreach ($trades as $t)
                                    @php($kind = $t->profileKind())
                                    @php($signup = ProviderProfiles::copy($kind)['signup'])
                                    @if ($signup && ProviderProfiles::get($kind))
                                        <template x-if="trade === @js($t->slug)">
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                @foreach ($signup as $name)
                                                    @php($field = ProviderProfiles::get($kind)['fields'][$name])
                                                    @php($key = "details.$kind.$name")
                                                    @php($input = "details[$kind][$name]")
                                                    @if ($field['type'] === 'multi')
                                                        <div class="sm:col-span-2">
                                                            <p class="field-label">{{ $field['label'] }} <span class="font-normal text-ink-400">(facultatif)</span></p>
                                                            <div class="flex flex-wrap gap-2">
                                                                @foreach ($field['options'] as $value => $label)
                                                                    <label class="cursor-pointer rounded-full border border-ink-200 bg-white px-3.5 py-1.5 text-sm transition has-checked:border-accent-500 has-checked:bg-accent-600 has-checked:text-white">
                                                                        <input type="checkbox" name="{{ $input }}[]" value="{{ $value }}" class="sr-only" @checked(in_array($value, (array) old($key, [])))>
                                                                        {{ $label }}
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                            @error($key) <p class="field-error">{{ $message }}</p> @enderror
                                                        </div>
                                                    @elseif ($field['type'] === 'bool')
                                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm font-medium transition has-checked:border-accent-500 has-checked:bg-accent-50">
                                                            <input type="checkbox" name="{{ $input }}" value="1" class="checkbox" @checked(old($key))>
                                                            {{ $field['label'] }}
                                                        </label>
                                                    @else
                                                        <x-field :name="$input" :label="$field['label'].' (facultatif)'" :type="in_array($field['type'], ['int', 'decimal']) ? 'number' : 'text'" min="0" :placeholder="$field['placeholder'] ?? ''" />
                                                    @endif
                                                @endforeach
                                            </div>
                                        </template>
                                    @endif
                                @endforeach

                                <p class="text-sm text-ink-500">Vous compléterez le reste de votre fiche (photos, horaires, services) après l'inscription.</p>
                            </div>
                        </template>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="field-label" x-text="type === 'pro' ? 'Nom du responsable' : 'Nom complet'">Nom complet</label>
                                <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required @class(['input', 'input-error' => $errors->has('name')])>
                                @error('name') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <x-field name="email" type="email" label="Email" autocomplete="email" placeholder="vous@exemple.com" required />
                        </div>

                        {{-- Téléphone : obligatoire pour les pros (les clients les appellent), facultatif sinon --}}
                        <div>
                            <label for="phone" class="field-label"><span x-text="type === 'pro' ? (current?.phone ?? 'Téléphone') : 'Téléphone'">Téléphone</span>
                                <span x-show="type !== 'pro'" class="font-normal text-ink-400">{{ feature('client_phone_required') ? '' : '(facultatif)' }}</span>
                            </label>
                            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="70 12 34 56" value="{{ old('phone') }}"
                                   @class(['input', 'input-error' => $errors->has('phone')]) :required="type === 'pro' || @js(feature('client_phone_required'))">
                            <p class="mt-1 text-xs text-ink-400" x-text="type === 'pro' ? 'Affiché sur votre fiche : les clients vous appelleront à ce numéro.' : 'Utile pour être rappelé par un garage ou une boutique.'"></p>
                            @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <x-location-select :cities="$cities" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="password" type="password" label="Mot de passe" autocomplete="new-password" hint="8 caractères minimum" required />
                            <x-field name="password_confirmation" type="password" label="Confirmer le mot de passe" autocomplete="new-password" required />
                        </div>

                        <label class="flex items-start gap-2 text-sm text-ink-600">
                            <input type="checkbox" name="terms" value="1" class="checkbox mt-0.5" @checked(old('terms'))>
                            <span>J'accepte les <a href="{{ route('pages.show', 'conditions-utilisation') }}" target="_blank" class="text-accent-600 underline">conditions d'utilisation</a>
                                et la <a href="{{ route('pages.show', 'confidentialite') }}" target="_blank" class="text-accent-600 underline">politique de confidentialité</a>.</span>
                        </label>
                        @error('terms') <p class="field-error">{{ $message }}</p> @enderror

                        <button class="btn btn-primary w-full py-3.5 text-base" x-text="type === 'pro' ? 'Inscrire mon activité' : 'Créer mon compte'">Créer mon compte</button>
                    </div>
                </section>
            </form>
        </div>
    </div>
</x-layouts.app>
