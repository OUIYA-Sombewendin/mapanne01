<x-layouts.app title="Connexion">
    <div class="mx-auto max-w-sm">
        <div class="mb-6 text-center">
            <x-logo class="mx-auto size-12" />
            <h1 class="mt-3 text-2xl font-extrabold">Bon retour !</h1>
            <p class="text-sm text-ink-500">Connectez-vous avec votre adresse email.</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="card space-y-4 p-5">
            @csrf
            <x-field name="login" type="text" :label="feature('login_with_phone_enabled') ? 'Email (ou numéro de téléphone)' : 'Email'"
                     inputmode="email" autocomplete="username" placeholder="vous@exemple.com" required autofocus />
            <div>
                <x-field name="password" type="password" label="Mot de passe" autocomplete="current-password" required />
                <a href="{{ route('password.request') }}" class="mt-1 inline-block text-xs font-medium text-brand-600">Mot de passe oublié ?</a>
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-600">
                <input type="checkbox" name="remember" value="1" class="checkbox"> Rester connecté
            </label>
            <button class="btn btn-primary w-full py-3">Se connecter</button>
        </form>

        <p class="mt-5 text-center text-sm text-ink-500">
            Pas encore de compte ? <a href="{{ route('register') }}" class="font-semibold text-brand-600">Créer un compte</a>
        </p>
    </div>
</x-layouts.app>
