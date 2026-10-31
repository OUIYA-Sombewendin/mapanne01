<x-layouts.app title="Espace pro">
    <h1 class="mb-1 text-2xl font-extrabold">{{ $provider->business_name }}</h1>
    <p class="mb-4 text-sm text-ink-500">{{ $provider->type->label() }}</p>
    @include('pro._nav')

    {{-- Statut de validation --}}
    @switch($provider->status)
        @case(\App\Enums\ProviderStatus::Pending)
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="font-semibold text-amber-900">⏳ Fiche en cours de vérification</p>
                <p class="mt-1 text-sm text-amber-800">L'équipe MaPanne vérifie vos informations. Votre fiche n'est pas encore visible dans les recherches. Une fiche complète est validée plus vite.</p>
            </div>
            @break
        @case(\App\Enums\ProviderStatus::Rejected)
            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4">
                <p class="font-semibold text-red-900">Fiche refusée</p>
                <p class="mt-1 text-sm text-red-800"><strong>Motif :</strong> {{ $provider->rejection_reason }}</p>
                @if (auth()->user()->canShop('profile'))
                <form method="POST" action="{{ route('pro.resubmit') }}" class="mt-3">
                    @csrf
                    <button class="btn btn-danger btn-sm">J'ai corrigé, renvoyer en vérification</button>
                </form>
                @endif
            </div>
            @break
        @case(\App\Enums\ProviderStatus::Suspended)
            <div class="mb-5 rounded-2xl border border-ink-200 bg-ink-100 p-4">
                <p class="font-semibold">Fiche suspendue</p>
                <p class="mt-1 text-sm text-ink-600">{{ $provider->rejection_reason }} — Contactez le support MaPanne.</p>
            </div>
            @break
        @default
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                ✓ Votre fiche est <strong>vérifiée et visible</strong> par les clients.
            </div>
    @endswitch

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Disponibilité instantanée --}}
        <section class="card p-5 lg:col-span-2">
            <h2 class="font-bold">Je suis…</h2>
            <p class="mb-3 text-sm text-ink-500">Mettez à jour votre statut en un geste. {{ $isOpen ? 'Vous êtes dans vos horaires d\'ouverture.' : 'Vous êtes hors de vos horaires d\'ouverture.' }}</p>
            <form method="POST" action="{{ route('pro.availability') }}" class="grid grid-cols-3 gap-2" @unless (auth()->user()->canShop('availability')) inert style="opacity:.6" @endunless>
                @csrf @method('PUT')
                @foreach (\App\Enums\Availability::cases() as $a)
                    <button name="availability" value="{{ $a->value }}" @class([
                        'flex flex-col items-center gap-1 rounded-xl border-2 p-3 text-sm font-semibold transition',
                        'border-ink-800 bg-ink-800 text-white' => $provider->availability === $a,
                        'border-ink-100 hover:border-ink-300' => $provider->availability !== $a,
                    ])>
                        <span class="size-3 rounded-full {{ $a->dot() }}"></span>{{ $a->label() }}
                    </button>
                @endforeach
            </form>
        </section>

        {{-- Statistiques --}}
        <section class="card grid grid-cols-3 gap-2 p-5 text-center lg:grid-cols-1 lg:text-left">
            <div><p class="text-2xl font-extrabold">{{ number_format($provider->rating_avg, 1, ',', '') }}</p><p class="text-xs text-ink-500">Note moyenne</p></div>
            <div><p class="text-2xl font-extrabold">{{ $provider->reviews_count }}</p><p class="text-xs text-ink-500">Avis</p></div>
            <div><p class="text-2xl font-extrabold">{{ $favoritesCount }}</p><p class="text-xs text-ink-500">En favoris</p></div>
        </section>

        {{-- Complétude --}}
        <section class="card p-5">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-bold">Fiche complète à</h2>
                <span class="text-lg font-extrabold text-brand-600">{{ $completion['percent'] }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-ink-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $completion['percent'] }}%"></div></div>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($completion['checks'] as $label => $ok)
                    <li class="{{ $ok ? 'text-ink-400 line-through' : 'text-ink-700' }}">{{ $ok ? '✓' : '○' }} {{ $label }}</li>
                @endforeach
            </ul>
            @if (auth()->user()->canShop('profile'))
                <a href="{{ route('pro.profile') }}" class="btn btn-ghost btn-sm mt-3 w-full">Compléter ma fiche</a>
            @endif
        </section>

        {{-- Derniers avis --}}
        <section class="card p-5 lg:col-span-2">
            <h2 class="mb-2 font-bold">Derniers avis</h2>
            <ul class="divide-y divide-ink-50">
                @forelse ($latestReviews as $review)
                    <li class="py-2 text-sm">
                        <span class="text-amber-500">{{ str_repeat('★', $review->rating) }}</span>
                        <span class="font-medium">{{ \Illuminate\Support\Str::of($review->user->name)->before(' ') }}</span>
                        <span class="text-ink-400">· {{ $review->created_at->diffForHumans() }}</span>
                        @if ($review->comment) <p class="text-ink-600">{{ $review->comment }}</p> @endif
                    </li>
                @empty
                    <li class="py-2 text-sm text-ink-400">Pas encore d'avis. Invitez vos clients satisfaits à noter votre activité sur MaPanne.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
