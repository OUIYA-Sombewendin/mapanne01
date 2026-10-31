<x-layouts.app title="Mouvements de stock">
    <h1 class="mb-4 text-2xl font-extrabold">Historique du stock</h1>
    @include('pro.manage._nav')

    <form class="mb-3 flex flex-wrap gap-2">
        <select name="product" class="input max-w-xs">
            <option value="">Toutes les pièces</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}" @selected(request('product') == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        <select name="type" class="input max-w-44">
            <option value="">Tous les mouvements</option>
            <option value="in" @selected(request('type') === 'in')>Entrées</option>
            <option value="out" @selected(request('type') === 'out')>Sorties</option>
            <option value="adjust" @selected(request('type') === 'adjust')>Inventaires</option>
        </select>
        <button class="btn btn-dark">Filtrer</button>
        <a href="{{ route('pro.manage.stock') }}" class="btn btn-ghost">← Stock</a>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Pièce</th><th>Motif</th><th class="text-right">Quantité</th><th class="text-right">Stock après</th><th>Note</th><th>Par</th></tr></thead>
            <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td class="whitespace-nowrap text-ink-500">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                        <td class="font-medium">{{ $m->product?->name }}</td>
                        <td>{{ \App\Models\StockMovement::reasonLabel($m->reason) }}
                            @if ($m->invoice) <a href="{{ route('pro.manage.invoices.show', $m->invoice_id) }}" class="text-xs text-brand-600">{{ $m->invoice->number }}</a> @endif</td>
                        <td @class(['text-right font-bold', 'text-emerald-600' => $m->quantity > 0, 'text-red-600' => $m->quantity < 0])>{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                        <td class="text-right">{{ $m->stock_after }}</td>
                        <td class="text-ink-500">{{ $m->note }}</td>
                        <td class="text-ink-500">{{ $m->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-ink-400">Aucun mouvement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>
</x-layouts.app>
