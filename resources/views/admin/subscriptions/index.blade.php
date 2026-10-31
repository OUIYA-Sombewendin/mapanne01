<x-layouts.admin title="Abonnements">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">Abonnements</h1>
            <p class="text-sm text-ink-500">Encaissé ce mois : <strong>{{ number_format($revenue, 0, ',', ' ') }} FCFA</strong>. Le paiement se fait hors plateforme : activez l'offre après réception.</p>
        </div>
        <a href="{{ route('admin.plans.index') }}" class="btn btn-ghost"><x-icon name="layers" /> Gérer les offres</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['pending' => 'À activer', 'active' => 'En cours', 'expiring' => 'Échéance proche', 'expired' => 'Expirés', 'cancelled' => 'Annulés', 'all' => 'Tous'] as $key => $label)
            <a href="{{ route('admin.subscriptions.index', ['status' => $key] + request()->only(['q', 'plan', 'payment', 'sort', 'dir'])) }}" @class([
                'chip px-3 py-1.5 text-sm',
                'on-color bg-ink-800 text-white' => $status === $key,
                'bg-white text-ink-600 ring-1 ring-ink-200' => $status !== $key,
            ])>{{ $label }} @isset($counts[$key]) <span class="opacity-70">{{ $counts[$key] }}</span> @endisset</a>
        @endforeach
    </div>

    <x-filter-bar :keys="['q', 'plan', 'payment']" :keep="['status']">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-56" placeholder="Prestataire…">
        <select name="plan" class="input-sm">
            <option value="">Toutes les offres</option>
            @foreach ($plans as $plan) <option value="{{ $plan->id }}" @selected(request('plan') == $plan->id)>{{ $plan->name }}</option> @endforeach
        </select>
        <select name="payment" class="input-sm">
            <option value="">Tous les paiements</option>
            @foreach ($paymentMethods as $m) <option @selected(request('payment') === $m)>{{ $m }}</option> @endforeach
        </select>
        <select name="sort" class="input-sm ml-auto" aria-label="Trier">
            @foreach (['date' => 'Trier : demande', 'start' => 'Trier : début', 'end' => 'Trier : échéance', 'amount' => 'Trier : montant'] as $k => $l)
                <option value="{{ $k }}" @selected(request('sort', 'date') === $k)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="dir" class="input-sm" aria-label="Ordre">
            <option value="desc" @selected(request('dir', 'desc') === 'desc')>Décroissant</option>
            <option value="asc" @selected(request('dir') === 'asc')>Croissant</option>
        </select>
    </x-filter-bar>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-3">
            @forelse ($subscriptions as $s)
                <article class="card p-4" x-data="{ open: false }">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">
                                @can('providers.view')<a href="{{ route('admin.providers.show', $s->provider) }}" class="hover:text-brand-600">{{ $s->provider->business_name }}</a>@else{{ $s->provider->business_name }}@endcan
                                · {{ $s->plan->name }}
                                <span class="chip {{ $s->statusClass() }}">{{ $s->statusLabel() }}</span>
                            </p>
                            <p class="text-xs text-ink-500">
                                {{ number_format($s->amount, 0, ',', ' ') }} F
                                @if ($s->starts_at) · du {{ $s->starts_at->format('d/m/Y') }} @endif
                                @if ($s->ends_at) au {{ $s->ends_at->format('d/m/Y') }} @endif
                                @if ($s->payment_method) · {{ $s->payment_method }} @endif
                                @if ($s->payment_reference) · réf. {{ $s->payment_reference }} @endif
                                @if ($s->activator) · activé par {{ $s->activator->name }} @endif
                                · demandé {{ $s->created_at->diffForHumans() }} · {{ \App\Support\Phone::format($s->provider->phone) }}
                            </p>
                        </div>
                        @if ($s->status === 'pending')
                            <button @click="open = !open" class="btn btn-primary btn-sm">Activer</button>
                        @endif
                        @if (in_array($s->status, ['pending', 'active'], true))
                            <form method="POST" action="{{ route('admin.subscriptions.cancel', $s) }}" onsubmit="return confirm('Annuler cet abonnement ?')">@csrf<button class="btn btn-ghost btn-sm">Annuler</button></form>
                        @endif
                    </div>
                    @if ($s->status === 'pending')
                        <form x-cloak x-show="open" method="POST" action="{{ route('admin.subscriptions.activate', $s) }}" class="mt-3 grid gap-2 rounded-xl bg-ink-50 p-3 sm:grid-cols-4">
                            @csrf
                            <input type="number" name="amount" value="{{ $s->amount }}" min="0" class="input" placeholder="Montant reçu" aria-label="Montant reçu">
                            <select name="payment_method" class="input" aria-label="Moyen de paiement">
                                @foreach ($paymentMethods as $m) <option>{{ $m }}</option> @endforeach
                            </select>
                            <input name="payment_reference" class="input" placeholder="Référence de transaction">
                            <button class="btn btn-primary">Confirmer le paiement</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="card p-6 text-center text-ink-400">Aucun abonnement dans cette liste.</p>
            @endforelse
            {{ $subscriptions->links() }}
        </div>

        <form method="POST" action="{{ route('admin.subscriptions.store') }}" class="card h-fit space-y-3 p-5">
            @csrf
            <h2 class="font-bold">Attribuer une offre</h2>
            <p class="text-xs text-ink-500">Paiement reçu au bureau, période d'essai, geste commercial…</p>
            <select name="provider_id" class="input" required aria-label="Prestataire">
                <option value="">Prestataire…</option>
                @foreach ($providers as $p) <option value="{{ $p->id }}">{{ $p->business_name }}</option> @endforeach
            </select>
            <select name="plan_id" class="input" required aria-label="Offre">
                @foreach ($plans as $plan) <option value="{{ $plan->id }}">{{ $plan->name }} — {{ $plan->priceLabel() }}</option> @endforeach
            </select>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" name="amount" min="0" class="input" placeholder="Montant reçu">
                <input type="number" name="days" min="0" class="input" placeholder="Durée (jours)">
            </div>
            <select name="payment_method" class="input" aria-label="Moyen de paiement">
                <option value="">Moyen de paiement</option>
                @foreach ($paymentMethods as $m) <option>{{ $m }}</option> @endforeach
            </select>
            <input name="payment_reference" class="input" placeholder="Référence (facultatif)">
            <button class="btn btn-primary w-full">Activer</button>
            <p class="text-xs text-ink-400">Durée vide = durée de l'offre. L'abonnement en cours du prestataire est remplacé.</p>
        </form>
    </div>
</x-layouts.admin>
