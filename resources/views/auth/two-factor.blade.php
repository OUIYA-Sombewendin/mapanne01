<x-layouts.auth title="Vérification">
    <div class="mx-auto max-w-sm py-6">
        <div class="card p-6">
            <x-logo class="mx-auto size-12" />
            <h1 class="mt-3 text-center text-xl font-extrabold">Vérification de connexion</h1>
            <p class="mt-1 text-center text-sm text-ink-500">Un code à 6 chiffres vient d'être envoyé à <strong>{{ $email }}</strong>. Il est valable 10 minutes.</p>
            @unless (\App\Support\Delivery::mailReady())
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-900 ring-1 ring-amber-200">⚠️ L'envoi d'emails n'est pas en service : le code n'a pas pu partir.@if (app()->isLocal()) En développement, il figure dans storage/logs/laravel.log.@endif</p>
            @endunless

            <form method="POST" action="{{ route('login.two-factor') }}" class="mt-5 space-y-3">
                @csrf
                <input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus
                       class="input text-center text-2xl font-bold tracking-[0.5em]" placeholder="••••••" aria-label="Code de vérification">
                @error('code') <p class="field-error text-center">{{ $message }}</p> @enderror
                <button class="btn btn-primary w-full py-3">Valider</button>
            </form>

            <form method="POST" action="{{ route('login.two-factor.resend') }}" class="mt-3 text-center">
                @csrf
                <button class="text-sm text-brand-600 hover:underline">Renvoyer un code</button>
            </form>
            <p class="mt-4 text-center"><x-back :href="route('login')" label="Retour à la connexion" /></p>
        </div>
    </div>
</x-layouts.auth>
