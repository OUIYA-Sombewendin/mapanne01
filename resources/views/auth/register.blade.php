<x-layouts.app title="Créer un compte">
    <div class="mx-auto max-w-lg" x-data="{ type: @js(old('account_type', $accountType)) }">
        <h1 class="mb-1 text-center text-2xl font-extrabold">Créer un compte</h1>
        <p class="mb-5 text-center text-sm text-ink-500">Gratuit, en moins d'une minute.</p>

        {{-- Choix du profil (masqué si un seul type d'inscription est ouvert par l'admin) --}}
        @if ($clientsOpen && $prosOpen)
            <div class="mb-4 grid grid-cols-2 gap-2">
                <button type="button" @click="type = 'client'" :class="type === 'client' ? 'ring-2 ring-brand-500 bg-brand-50' : ''" class="card p-4 text-left">
                    <span class="text-2xl">🚗</span>
                    <span class="mt-1 block font-semibold">Je cherche de l'aide</span>
                    <span class="block text-xs text-ink-500">Automobiliste, motocycliste</span>
                </button>
                <button type="button" @click="type = 'pro'" :class="type === 'pro' ? 'ring-2 ring-brand-500 bg-brand-50' : ''" class="card p-4 text-left">
                    <span class="text-2xl">🔧</span>
                    <span class="mt-1 block font-semibold">Je suis un pro</span>
                    <span class="block text-xs text-ink-500">Mécanicien, garage, station, pièces</span>
                </button>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="card space-y-4 p-5">
            @csrf
            <input type="hidden" name="account_type" :value="type">
            @error('account_type') <p class="field-error">{{ $message }}</p> @enderror

            <template x-if="type === 'pro'">
                <div class="space-y-4 rounded-xl bg-ink-50 p-4">
                    <p class="text-sm text-ink-600">Votre fiche sera <strong>vérifiée par l'équipe {{ setting('site_name') }}</strong> avant d'apparaître dans les recherches.</p>
                    <div>
                        <label class="field-label" for="provider_type">Type d'activité</label>
                        <select id="provider_type" name="provider_type" class="input" required>
                            <option value="">Choisir…</option>
                            @foreach (\App\Models\ProviderType::active() as $t)
                                <option value="{{ $t->slug }}" @selected(old('provider_type') === $t->slug)>{{ $t->icon() }} {{ $t->label() }}</option>
                            @endforeach
                        </select>
                        @error('provider_type') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <x-field name="business_name" label="Nom de l'activité" placeholder="Ex. Garage Wend-Kuuni" required />
                </div>
            </template>

            <x-field name="name" label="Nom complet" autocomplete="name" required />
            <x-field name="email" type="email" label="Email" autocomplete="email" placeholder="vous@exemple.com" hint="Il sert à vous connecter et à récupérer votre compte." required />

            {{-- Téléphone en second : obligatoire pour les pros (les clients les appellent), facultatif sinon --}}
            <div>
                <label for="phone" class="field-label">Téléphone
                    <span x-show="type !== 'pro'" class="font-normal text-ink-400">{{ feature('client_phone_required') ? '' : '(facultatif)' }}</span>
                </label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="70 12 34 56" value="{{ old('phone') }}"
                       @class(['input', 'input-error' => $errors->has('phone')]) :required="type === 'pro' || @js(feature('client_phone_required'))">
                <p class="mt-1 text-xs text-ink-400" x-text="type === 'pro' ? 'Numéro affiché sur votre fiche : les clients vous appelleront.' : 'Utile pour être rappelé par un garage ou une boutique.'"></p>
                @error('phone') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <x-location-select :cities="$cities" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="password" type="password" label="Mot de passe" autocomplete="new-password" hint="8 caractères minimum" required />
                <x-field name="password_confirmation" type="password" label="Confirmer" autocomplete="new-password" required />
            </div>

            <label class="flex items-start gap-2 text-sm text-ink-600">
                <input type="checkbox" name="terms" value="1" class="checkbox mt-0.5" @checked(old('terms'))>
                <span>J'accepte les <a href="{{ route('pages.show', 'conditions-utilisation') }}" target="_blank" class="text-brand-600 underline">conditions d'utilisation</a>
                    et la <a href="{{ route('pages.show', 'confidentialite') }}" target="_blank" class="text-brand-600 underline">politique de confidentialité</a>.</span>
            </label>
            @error('terms') <p class="field-error">{{ $message }}</p> @enderror

            <button class="btn btn-primary w-full py-3" x-text="type === 'pro' ? 'Inscrire mon activité' : 'Créer mon compte'">Créer mon compte</button>
        </form>

        <p class="mt-5 text-center text-sm text-ink-500">Déjà inscrit ? <a href="{{ route('login') }}" class="font-semibold text-brand-600">Se connecter</a></p>
    </div>
</x-layouts.app>
