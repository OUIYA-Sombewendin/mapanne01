<x-layouts.pro title="Abonnement">
    <h1 class="mb-1 text-2xl font-extrabold">Mon abonnement</h1>
    <p class="mb-4 text-sm text-ink-500">
        Offre actuelle : <strong>{{ $currentPlan?->name ?? 'aucune' }}</strong>
        @if ($current?->ends_at) — jusqu'au {{ $current->ends_at->format('d/m/Y') }} ({{ $current->daysLeft() }} jour(s) restant(s)) @endif
    </p>

    @if ($rejected && ! $pending)
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            <p class="font-semibold">Paiement non validé pour l'offre {{ $rejected->plan->name }} ({{ $rejected->reviewed_at->format('d/m/Y') }})</p>
            <p class="mt-1">Motif : {{ $rejected->rejected_reason }}</p>
            <p class="mt-1">Refaites la demande avec la bonne preuve, ou contactez le support : {{ \App\Support\Phone::format(setting('support_phone')) }}.</p>
        </div>
    @endif

    @if ($pending)
        @if ($pending->hasProof())
            <div class="mb-5 flex flex-wrap items-center gap-4 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                <span class="text-3xl">🔎</span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">Paiement en cours de vérification : offre {{ $pending->plan->name }} ({{ $pending->plan->priceLabel() }})</p>
                    <p class="mt-1">{{ $pending->operatorLabel() }} depuis le {{ \App\Support\Phone::format($pending->payer_phone) }} · réf. {{ $pending->payment_reference }} · envoyé le {{ $pending->proof_submitted_at->format('d/m/Y à H:i') }}</p>
                </div>
                <a href="{{ route('pro.subscription.pay', $pending) }}" class="btn btn-ghost btn-sm">Corriger la preuve</a>
            </div>
        @else
            <div class="mb-5 flex flex-wrap items-center gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <span class="text-3xl">💳</span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">Offre {{ $pending->plan->name }} choisie : il reste à payer {{ $pending->plan->priceLabel() }}</p>
                    <p class="mt-1">Payez par Orange Money ou Moov Money, puis envoyez la preuve.</p>
                    <form method="POST" action="{{ route('pro.subscription.cancel', $pending) }}" class="mt-1">@csrf @method('DELETE')<button class="text-xs underline">Annuler la demande</button></form>
                </div>
                <a href="{{ route('pro.subscription.pay', $pending) }}" class="btn btn-primary">Payer et envoyer la preuve</a>
            </div>
        @endif
    @endif

    <div @class(['grid gap-4', 'md:grid-cols-2' => $plans->count() === 2, 'md:grid-cols-3' => $plans->count() >= 3])>
        @foreach ($plans as $plan)
            @php($isCurrent = $currentPlan?->id === $plan->id)
            <article @class(['card relative flex flex-col p-5', 'ring-2 ring-brand-500' => $plan->is_highlighted])>
                @if ($plan->is_highlighted) <span class="chip absolute -top-3 left-5 bg-brand-500 text-white">Recommandé</span> @endif
                <h2 class="text-lg font-bold">{{ $plan->name }}</h2>
                <p class="text-sm text-ink-500">{{ $plan->description }}</p>
                <p class="my-3 text-2xl font-extrabold">{{ $plan->priceLabel() }}</p>
                <ul class="flex-1 space-y-1.5 text-sm">
                    @foreach (\App\Support\PlanFeatures::features() as $key => $feature)
                        @continue(($feature['sellers_only'] ?? false) && ! $provider->canSellParts())
                        <li @class(['flex gap-2', 'text-ink-300 line-through' => ! $plan->includes($key)])>
                            <span>{{ $plan->includes($key) ? '✓' : '–' }}</span>{{ $feature['label'] }}
                        </li>
                    @endforeach
                    @if ($provider->canSellParts())
                        <li class="flex gap-2"><span>✓</span>{{ $plan->limit('max_products') ? $plan->limit('max_products').' produits max.' : 'Produits illimités' }}</li>
                    @endif
                </ul>
                @if ($isCurrent)
                    <p class="mt-4 rounded-xl bg-emerald-50 py-2.5 text-center text-sm font-semibold text-emerald-700">Votre offre actuelle</p>
                @else
                    <form method="POST" action="{{ route('pro.subscription.store') }}" class="mt-4">
                        @csrf <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        <button @class(['btn w-full', 'btn-primary' => $plan->is_highlighted, 'btn-dark' => ! $plan->is_highlighted])>{{ $plan->price ? 'Choisir cette offre' : 'Passer à cette offre' }}</button>
                    </form>
                @endif
            </article>
        @endforeach
    </div>

    @if ($history->isNotEmpty())
        <section class="card mt-6 overflow-x-auto p-5">
            <h2 class="mb-2 font-bold">Historique</h2>
            <table class="data-table">
                <thead><tr><th>Offre</th><th>Statut</th><th>Début</th><th>Fin</th><th class="text-right">Montant</th></tr></thead>
                <tbody>
                    @foreach ($history as $s)
                        <tr>
                            <td>{{ $s->plan->name }}</td>
                            <td><span class="chip {{ $s->statusClass() }}">{{ $s->statusLabel() }}</span></td>
                            <td>{{ $s->starts_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $s->ends_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-right">{{ number_format($s->amount, 0, ',', ' ') }} F</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</x-layouts.pro>
