<x-layouts.auth title="Nouveau mot de passe">
    <div class="mx-auto max-w-sm">
        <h1 class="mb-5 text-center text-2xl font-extrabold">Choisir un nouveau mot de passe</h1>
        <form method="POST" action="{{ route('password.update') }}" class="card space-y-4 p-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-field name="email" type="email" label="Email" :value="$email" autocomplete="email" required />
            <x-field name="password" type="password" label="Nouveau mot de passe" autocomplete="new-password" hint="8 caractères minimum" required />
            <x-field name="password_confirmation" type="password" label="Confirmer" autocomplete="new-password" required />
            <button class="btn btn-primary w-full py-3">Enregistrer</button>
        </form>
    </div>
</x-layouts.auth>
