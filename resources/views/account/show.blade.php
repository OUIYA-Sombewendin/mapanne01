<x-layouts.app title="Profil et sécurité">
    <div class="mb-5 flex items-center gap-3">
        <span class="flex size-14 items-center justify-center rounded-full bg-ink-800 text-lg font-bold text-white">{{ $user->initials() }}</span>
        <div>
            <h1 class="text-xl font-extrabold">{{ $user->name }}</h1>
            <p class="text-sm text-ink-500">{{ collect([\App\Support\Phone::format($user->phone), $user->role->label()])->filter()->implode(' · ') }}</p>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="card p-5">
            <h2 class="mb-3 font-bold">👤 Mes informations</h2>
            <form method="POST" action="{{ route('account.update') }}" class="space-y-3">
                @csrf @method('PUT')
                <x-field name="name" label="Nom complet" :value="$user->name" required />
                <x-field name="email" type="email" label="Email" :value="$user->email" :hint="$user->hasVerifiedEmail() ? 'Adresse confirmée ✓' : 'Adresse non confirmée'" required />
                <x-field name="phone" type="tel" label="Téléphone (facultatif)" :value="$user->phone" />
                <x-location-select :cities="$cities" :city="$user->city_id" :neighborhood="$user->neighborhood_id" />
                <button class="btn btn-primary">Enregistrer</button>
            </form>
        </section>

        <div class="space-y-5">
            <section class="card p-5">
                <h2 class="mb-3 font-bold">🔒 Mot de passe</h2>
                <form method="POST" action="{{ route('account.password') }}" class="space-y-3">
                    @csrf @method('PUT')
                    <x-field name="current_password" type="password" label="Mot de passe actuel" autocomplete="current-password" required />
                    <x-field name="password" type="password" label="Nouveau mot de passe" autocomplete="new-password" hint="8 caractères minimum" required />
                    <x-field name="password_confirmation" type="password" label="Confirmer" autocomplete="new-password" required />
                    <button class="btn btn-dark">Changer le mot de passe</button>
                </form>
                <p class="mt-3 text-xs text-ink-500">Après le changement, vos autres appareils sont déconnectés.</p>
            </section>

            @if (\App\Support\Channels::pushReady())
                <section class="card p-5">
                    <h2 class="mb-1 font-bold">🔔 Notifications sur ce téléphone</h2>
                    <p class="mb-3 text-sm text-ink-500">Soyez prévenu quand un pro accepte votre dépannage ou qu'une boutique répond.</p>
                    <x-push-toggle />
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
