@php($money = fn ($v) => \App\Support\ShopReport::money($v))
<x-layouts.pro title="Gestion">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-extrabold">Gestion de {{ $provider->business_name }}</h1>
        <div class="flex gap-2">
            @if ($provider->hasPlanFeature('invoices'))
                <a href="{{ route('pro.manage.invoices.create') }}" class="btn btn-primary btn-sm">+ Nouvelle vente</a>
            @endif
            @if ($provider->hasPlanFeature('expenses'))
                <a href="{{ route('pro.manage.expenses.index') }}#ajout" class="btn btn-ghost btn-sm">+ Dépense</a>
            @endif
        </div>
    </div>
    <p class="mb-4 text-sm text-ink-500">Votre activité de {{ now()->translatedFormat('F Y') }}. Ces informations sont privées : ni les clients ni les autres pros ne les voient.</p>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([
            ['Ventes du mois', $money($totals['revenue']), $totals['invoices'].' facture(s)', 'text-ink-900'],
            ['Marge brute', $money($totals['gross']), 'ventes − coût des pièces', 'text-ink-900'],
            ['Dépenses', $money($totals['expenses']), 'loyer, salaires…', 'text-ink-900'],
            ['Bénéfice net', $money($totals['net']), 'marge − dépenses', $totals['net'] >= 0 ? 'text-emerald-600' : 'text-red-600'],
        ] as [$label, $value, $hint, $color])
            <div class="card p-4">
                <p class="text-xs text-ink-500">{{ $label }}</p>
                <p class="text-xl font-extrabold md:text-2xl {{ $color }}">{{ $value }}</p>
                <p class="text-xs text-ink-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <h2 class="mb-3 font-bold">Ventes jour par jour</h2>
            @include('pro.manage._bars', ['series' => $series])
            <div class="mt-4 flex flex-wrap gap-4 text-sm">
                <span>Encaissé : <strong>{{ $money($totals['collected']) }}</strong></span>
                <span>Reste à encaisser (toutes factures) : <strong @class(['text-red-600' => $unpaid > 0])>{{ $money($unpaid) }}</strong></span>
            </div>
        </section>

        @if ($stockValue)
            <section class="card p-5">
                <h2 class="mb-3 font-bold">Stock</h2>
                <p class="text-sm text-ink-500">{{ number_format($stockValue['units'], 0, ',', ' ') }} pièces en boutique</p>
                <p class="mt-1 text-sm">Valeur d'achat : <strong>{{ $money($stockValue['cost']) }}</strong></p>
                <p class="text-sm">Valeur de vente : <strong>{{ $money($stockValue['retail']) }}</strong></p>
                <h3 class="mt-4 mb-2 text-sm font-semibold">À réapprovisionner</h3>
                <ul class="space-y-1 text-sm">
                    @forelse ($lowStock as $p)
                        <li class="flex justify-between"><span class="truncate">{{ $p->name }}</span><span @class(['font-bold', 'text-red-600' => $p->stock === 0, 'text-amber-600' => $p->stock > 0])>{{ $p->stock }}</span></li>
                    @empty
                        <li class="text-ink-400">Aucune alerte de stock.</li>
                    @endforelse
                </ul>
                <a href="{{ route('pro.manage.stock') }}" class="mt-3 inline-block text-sm font-medium text-brand-600">Gérer le stock →</a>
            </section>
        @endif

        @if ($recentInvoices->isNotEmpty())
            <section class="card p-5 lg:col-span-3">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-bold">Dernières ventes et devis</h2>
                    <a href="{{ route('pro.manage.invoices.index') }}" class="text-sm text-brand-600">Tout voir</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>N°</th><th>Date</th><th>Client</th><th class="text-right">Total</th><th>Statut</th></tr></thead>
                        <tbody>
                            @foreach ($recentInvoices as $inv)
                                <tr>
                                    <td><a href="{{ route('pro.manage.invoices.show', $inv) }}" class="font-semibold text-brand-600">{{ $inv->number }}</a></td>
                                    <td>{{ $inv->issued_at->format('d/m/Y') }}</td>
                                    <td>{{ $inv->customer_name ?: '—' }}</td>
                                    <td class="text-right font-semibold">{{ $money($inv->total) }}</td>
                                    <td><span class="chip {{ $inv->statusClass() }}">{{ $inv->statusLabel() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts.pro>
