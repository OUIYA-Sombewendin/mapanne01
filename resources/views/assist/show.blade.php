@php
    $steps = [
        'open' => ['📡', 'Demande envoyée', $assist->notified_count.' prestataire(s) proche(s) prévenu(s).'],
        'accepted' => ['🤝', 'Acceptée', 'Le prestataire va vous appeler.'],
        'on_way' => ['🛵', 'En route', 'Le prestataire arrive.'],
        'done' => ['✅', 'Terminée', 'Intervention terminée.'],
    ];
    $order = array_keys($steps);
    $reached = array_search($assist->status, $order, true);
    $owner = in_array($assist->token, (array) session('assist_tokens', []), true) || ($assist->user_id && $assist->user_id === auth()->id());
@endphp
<x-layouts.app title="Suivi du dépannage">
    <div class="mx-auto max-w-xl" x-data="assistTracker(@js(route('assist.status', $assist)), @js($state))">
        <div class="card overflow-hidden">
            <div class="flex items-center gap-4 border-b border-ink-100 p-5" style="background: var(--section-bg, #f4f7fb)">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-white text-3xl shadow-sm">{{ $assist->problemIcon() }}</span>
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-extrabold">{{ $assist->problemLabel() }}</h1>
                    <p class="text-sm text-ink-500">{{ $assist->vehicleLabel() }} · demandé {{ $assist->created_at->diffForHumans() }}</p>
                </div>
                <span class="chip {{ $assist->statusClass() }}">{{ $assist->statusLabel() }}</span>
            </div>

            @if ($reached !== false)
                {{-- Étapes --}}
                <ol class="grid grid-cols-4 gap-1 p-5">
                    @foreach ($steps as $key => [$icon, $label, $text])
                        @php($done = array_search($key, $order, true) <= $reached)
                        <li class="text-center">
                            <span @class(['mx-auto flex size-11 items-center justify-center rounded-full text-xl', 'bg-accent-600 text-white' => $done, 'bg-ink-100 grayscale' => ! $done])>{{ $icon }}</span>
                            <span @class(['mt-1 block text-xs font-bold', 'text-accent-800' => $done, 'text-ink-400' => ! $done])>{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="px-5 pb-4 text-center text-sm text-ink-600">{{ $steps[$assist->status][2] }}</p>
            @endif

            @if ($assist->status === 'open')
                <div class="mx-5 mb-5 flex items-center gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900">
                    <span class="relative flex size-3"><span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex size-3 rounded-full bg-amber-500"></span></span>
                    En attente d'un prestataire. Cette page se met à jour toute seule.
                </div>
            @endif

            @if ($state['provider'])
                <div class="mx-5 mb-5 rounded-2xl border border-accent-100 bg-accent-50/60 p-4">
                    <p class="text-sm text-ink-500">Votre prestataire</p>
                    <p class="text-lg font-extrabold">{{ $state['provider']['name'] }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="tel:{{ $state['provider']['tel'] }}" class="btn btn-primary"><x-icon name="phone" /> Appeler {{ $state['provider']['phone'] }}</a>
                        <a href="{{ $state['provider']['url'] }}" class="btn btn-ghost">Voir sa fiche</a>
                    </div>
                    @if ($assist->status === 'done')
                        <p class="mt-3 text-sm text-ink-600">Tout s'est bien passé ? <a href="{{ $state['provider']['url'] }}#avis" class="font-semibold text-accent-700 underline">Laissez un avis</a> pour aider les autres conducteurs.</p>
                    @endif
                </div>
            @endif

            @if (in_array($assist->status, ['expired', 'cancelled'], true))
                <div class="mx-5 mb-5 rounded-2xl bg-ink-50 p-4 text-sm text-ink-700">
                    {{ $assist->status === 'expired' ? 'Aucun prestataire n\'a pu répondre à temps.' : 'Cette demande a été annulée.' }}
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('assist.create') }}" class="btn btn-primary">Nouvelle demande</a>
                        <a href="{{ route('search', ['type' => 'depanneur', 'open_now' => 1]) }}" class="btn btn-ghost">Appeler un dépanneur</a>
                    </div>
                </div>
            @endif
        </div>

        @if ($assist->isActive() && $owner)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                @auth <x-push-toggle compact /> @endauth
                <form method="POST" action="{{ route('assist.cancel', $assist) }}" onsubmit="return confirm('Annuler votre demande ?')">
                    @csrf
                    <button class="btn btn-ghost text-red-700">Annuler ma demande</button>
                </form>
            </div>
        @endif
        <p class="mt-4 text-center text-xs text-ink-400">Gardez ce lien pour suivre votre demande : {{ route('assist.show', $assist) }}</p>
    </div>
</x-layouts.app>
