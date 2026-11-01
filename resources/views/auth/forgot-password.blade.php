<x-layouts.auth title="Mot de passe oublié">
    <div class="mx-auto max-w-sm">
        <h1 class="mb-1 text-center text-2xl font-extrabold">Mot de passe oublié</h1>
        <p class="mb-5 text-center text-sm text-ink-500">Saisissez l'email de votre compte : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
        <form method="POST" action="{{ route('password.email') }}" class="card space-y-4 p-5">
            @csrf
            <x-field name="email" type="email" label="Email" autocomplete="email" required autofocus />
            <button class="btn btn-primary w-full py-3">Envoyer le lien</button>
        </form>
        <p class="mt-4 text-center text-xs text-ink-400">
            Plus accès à votre email ? Contactez le support : {{ setting('support_email') }} · {{ \App\Support\Phone::format(setting('support_phone')) }}
        </p>
        <p class="mt-4 text-center"><x-back :href="route('login')" label="Retour à la connexion" /></p>
    </div>
</x-layouts.auth>
