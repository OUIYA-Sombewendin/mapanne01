@php($f = fn ($v) => number_format($v, 0, ',', ' '))
@php($query = request()->only(['period', 'from', 'to']))
<x-layouts.app title="Rapports">
    <h1 class="mb-4 text-2xl font-extrabold">Rapports</h1>
    @include('pro.manage._nav')
    @include('pro.manage._period')

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([
            ['Chiffre d\'affaires', $totals['revenue'], $totals['invoices'].' facture(s)'],
            ['Coût des pièces vendues', $totals['cogs'], 'prix d\'achat'],
            ['Marge brute', $totals['gross'], $totals['revenue'] ? round($totals['gross'] / $totals['revenue'] * 100).' % du CA' : '—'],
            ['Dépenses', $totals['expenses'], 'loyer, salaires…'],
        ] as [$label, $value, $hint])
            <div class="card p-4"><p class="text-xs text-ink-500">{{ $label }}</p><p class="text-xl font-extrabold">{{ $f($value) }} F</p><p class="text-xs text-ink-400">{{ $hint }}</p></div>
        @endforeach
    </div>

    <div @class(['card mt-3 flex flex-wrap items-center justify-between gap-3 p-5', 'bg-emerald-50 ring-emerald-200' => $totals['net'] >= 0, 'bg-red-50 ring-red-200' => $totals['net'] < 0])>
        <div>
            <p class="text-sm text-ink-600">Bénéfice net de la période</p>
            <p @class(['text-3xl font-extrabold', 'text-emerald-700' => $totals['net'] >= 0, 'text-red-700' => $totals['net'] < 0])>{{ $f($totals['net']) }} FCFA</p>
        </div>
        <div class="text-sm text-ink-600">
            <p>Encaissé : <strong>{{ $f($totals['collected']) }} F</strong> · reste à encaisser : <strong>{{ $f($totals['receivable']) }} F</strong></p>
            <p>Achats de stock sur la période : {{ $f($totals['purchases']) }} F · remises accordées : {{ $f($totals['discounts']) }} F</p>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <h2 class="mb-3 font-bold">Évolution des ventes</h2>
            @include('pro.manage._bars', ['series' => $series])
        </section>
        <section class="card p-5">
            <h2 class="mb-3 font-bold">Dépenses par catégorie</h2>
            <ul class="space-y-1 text-sm">
                @forelse ($byCategory as $cat => $total)
                    <li class="flex justify-between"><span>{{ $cat }}</span><strong>{{ $f($total) }} F</strong></li>
                @empty
                    <li class="text-ink-400">Aucune dépense.</li>
                @endforelse
            </ul>
        </section>
        <section class="card p-5 lg:col-span-2">
            <h2 class="mb-3 font-bold">Meilleures ventes</h2>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Article</th><th class="text-right">Qté</th><th class="text-right">CA</th><th class="text-right">Marge</th></tr></thead>
                    <tbody>
                        @forelse ($top as $row)
                            <tr><td>{{ $row->description }}</td><td class="text-right">{{ $row->qty }}</td><td class="text-right">{{ $f($row->revenue) }}</td><td class="text-right">{{ $f($row->margin) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-ink-400">Aucune vente.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card space-y-2 p-5">
            <h2 class="font-bold">Exporter (Excel)</h2>
            <a href="{{ route('pro.manage.reports.export', ['dataset' => 'ventes'] + $query) }}" class="btn btn-ghost w-full justify-start">⬇ Ventes détaillées</a>
            <a href="{{ route('pro.manage.reports.export', ['dataset' => 'depenses'] + $query) }}" class="btn btn-ghost w-full justify-start">⬇ Dépenses</a>
            @if ($stockValue)
                <a href="{{ route('pro.manage.reports.export', ['dataset' => 'stock']) }}" class="btn btn-ghost w-full justify-start">⬇ État du stock</a>
                <p class="pt-2 text-sm text-ink-500">Stock actuel : {{ $f($stockValue['cost']) }} F au prix d'achat.</p>
            @endif
        </section>
    </div>
    <p class="mt-4 text-xs text-ink-400">Marge brute = ventes − prix d'achat des pièces vendues. Bénéfice net = marge brute − dépenses. Renseignez le prix d'achat de vos pièces pour des chiffres exacts.</p>
</x-layouts.app>
