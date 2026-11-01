<x-layouts.admin title="Abonnements">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">Abonnements</h1>
            <p class="text-sm text-ink-500">Encaissé ce mois : <strong>{{ number_format($revenue, 0, ',', ' ') }} FCFA</strong>. Paiement hors plateforme (Orange Money, Moov Money) : vérifiez la preuve sur votre téléphone opérateur, puis activez ou refusez.</p>
        </div>
        <a href="{{ route('admin.plans.index') }}" class="btn btn-ghost"><x-icon name="layers" /> Gérer les offres</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['proof' => 'Preuves à vérifier', 'pending' => 'En attente de paiement', 'active' => 'En cours', 'expiring' => 'Échéance proche', 'expired' => 'Expirés', 'rejected' => 'Refusés', 'cancelled' => 'Annulés', 'all' => 'Tous'] as $key => $label)
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
                            <button @click="open = !open" class="btn btn-primary btn-sm">{{ $s->hasProof() ? 'Valider ou refuser' : 'Activer' }}</button>
                        @endif
                        @if (in_array($s->status, ['pending', 'active'], true))
                            <form method="POST" action="{{ route('admin.subscriptions.cancel', $s) }}" onsubmit="return confirm('Annuler cet abonnement ?')">@csrf<button class="btn btn-ghost btn-sm">Annuler</button></form>
                        @endif
                    </div>
                    @if ($s->hasProof())
                        {{-- Preuve envoyée par le prestataire --}}
                        <div class="mt-3 flex flex-wrap items-center gap-4 rounded-xl border border-sky-100 bg-sky-50/70 p-3">
                            @if ($s->proof_path)
                                <a href="{{ route('admin.subscriptions.proof', $s) }}" target="_blank" rel="noopener" class="shrink-0" title="Ouvrir la capture">
                                    <img src="{{ route('admin.subscriptions.proof', $s) }}" alt="Capture du paiement" class="h-24 w-20 rounded-lg object-cover shadow ring-1 ring-sky-200" loading="lazy">
                                </a>
                            @endif
                            <dl class="grid flex-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                <div><dt class="inline text-ink-500">Opérateur :</dt> <dd class="inline font-semibold">{{ $s->operatorLabel() }}</dd></div>
                                <div><dt class="inline text-ink-500">Payé depuis :</dt> <dd class="inline font-semibold">{{ \App\Support\Phone::format($s->payer_phone) }}</dd></div>
                                <div><dt class="inline text-ink-500">Référence :</dt> <dd class="inline font-mono font-semibold">{{ $s->payment_reference }}</dd></div>
                                <div><dt class="inline text-ink-500">Envoyée :</dt> <dd class="inline">{{ $s->proof_submitted_at->format('d/m/Y H:i') }}</dd></div>
                            </dl>
                            @if ($s->status === 'pending' && $s->payment_operator && \App\Models\Subscription::referenceUsed($s->payment_operator, $s->payment_reference, $s->id))
                                <p class="w-full rounded-lg bg-red-100 px-3 py-2 text-sm font-semibold text-red-800">⚠️ Cette référence figure déjà sur un autre abonnement.</p>
                            @endif
                        </div>
                    @endif
                    @if ($s->rejected_reason)
                        <p class="mt-2 text-sm text-red-700">Motif du refus : {{ $s->rejected_reason }}</p>
                    @endif
                    @if ($s->status === 'pending')
                        <div x-cloak x-show="open" class="mt-3 space-y-2 rounded-xl bg-ink-50 p-3">
                            <form method="POST" action="{{ route('admin.subscriptions.activate', $s) }}" class="grid gap-2 sm:grid-cols-4">
                                @csrf
                                <input type="number" name="amount" value="{{ $s->amount }}" min="0" class="input" placeholder="Montant reçu" aria-label="Montant reçu">
                                <select name="payment_method" class="input" aria-label="Moyen de paiement">
                                    @foreach ($paymentMethods as $m) <option @selected($m === $s->payment_method)>{{ $m }}</option> @endforeach
                                </select>
                                <input name="payment_reference" value="{{ $s->payment_reference }}" class="input" placeholder="Référence de transaction">
                                <button class="btn btn-primary">Paiement reçu : activer</button>
                            </form>
                            @if ($s->hasProof())
                                <form method="POST" action="{{ route('admin.subscriptions.reject', $s) }}" class="flex flex-wrap gap-2">
                                    @csrf
                                    <input name="reason" class="input flex-1" placeholder="Motif du refus (ex. paiement introuvable, montant incomplet)" required maxlength="255">
                                    <button class="btn btn-ghost text-red-700">Refuser le paiement</button>
                                </form>
                            @endif
                        </div>
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
