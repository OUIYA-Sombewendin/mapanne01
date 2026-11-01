<x-layouts.auth title="Confirmez votre email">
    <div class="mx-auto max-w-md text-center">
        <p class="text-5xl">✉️</p>
        <h1 class="mt-3 text-2xl font-extrabold">Confirmez votre adresse email</h1>
        <p class="mt-2 text-ink-500">Un lien de confirmation est envoyé à <strong class="text-ink-800">{{ auth()->user()->email }}</strong>. Cliquez dessus pour activer toutes les fonctionnalités de votre compte.</p>
        @unless (\App\Support\Delivery::mailReady())
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">⚠️ L'envoi d'emails n'est pas encore en service : le lien ne peut pas vous parvenir pour l'instant. Vous pouvez continuer à utiliser votre compte.</p>
        @endunless
        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button class="btn btn-primary">Renvoyer le lien</button>
        </form>
        <p class="mt-4 text-sm text-ink-500">Mauvaise adresse ? <a href="{{ route('account.show') }}" class="text-brand-600">Modifiez-la dans votre compte</a>.</p>
        <p class="mt-2 text-sm"><a href="{{ auth()->user()->homeRoute() }}" class="font-semibold text-brand-600">Continuer vers mon espace →</a></p>
    </div>
</x-layouts.auth>
