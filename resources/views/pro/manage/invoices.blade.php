<x-layouts.pro title="Ventes et factures">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-extrabold">Ventes et factures</h1>
        <div class="flex gap-2">
            <a href="{{ route('pro.manage.invoices.create') }}" class="btn btn-primary btn-sm">+ Facture</a>
            <a href="{{ route('pro.manage.invoices.create', ['kind' => 'quote']) }}" class="btn btn-ghost btn-sm">+ Devis</a>
        </div>
    </div>
    <p class="mb-4 text-sm text-ink-500">Reste à encaisser : <strong @class(['text-red-600' => $unpaid > 0])>{{ number_format($unpaid, 0, ',', ' ') }} FCFA</strong></p>

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="flex rounded-xl bg-ink-100 p-1 text-sm font-medium">
            <a href="{{ route('pro.manage.invoices.index') }}" @class(['rounded-lg px-3 py-1.5', 'bg-white shadow-sm' => $kind === 'invoice', 'text-ink-500' => $kind !== 'invoice'])>Factures</a>
            <a href="{{ route('pro.manage.invoices.index', ['kind' => 'quote']) }}" @class(['rounded-lg px-3 py-1.5', 'bg-white shadow-sm' => $kind === 'quote', 'text-ink-500' => $kind !== 'quote'])>Devis</a>
        </div>
        <form class="flex flex-wrap gap-2">
            <input type="hidden" name="kind" value="{{ $kind }}">
            <input name="q" value="{{ request('q') }}" class="input max-w-56" placeholder="N° ou client">
            <select name="status" class="input max-w-44" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                @foreach (\App\Models\Invoice::STATUSES as $key => [$label])
                    @if ($kind === 'quote' ? in_array($key, ['draft', 'accepted', 'cancelled']) : ! in_array($key, ['draft', 'accepted']))
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>N°</th><th>Date</th><th>Client</th><th class="text-right">Total</th><th class="text-right">Reste dû</th><th>Statut</th></tr></thead>
            <tbody>
                @forelse ($invoices as $inv)
                    <tr class="cursor-pointer hover:bg-ink-50" onclick="location.href='{{ route('pro.manage.invoices.show', $inv) }}'">
                        <td><a href="{{ route('pro.manage.invoices.show', $inv) }}" class="font-semibold text-brand-600">{{ $inv->number }}</a></td>
                        <td class="whitespace-nowrap">{{ $inv->issued_at->format('d/m/Y') }}</td>
                        <td>{{ $inv->customer_name ?: '—' }} <span class="block text-xs text-ink-400">{{ \App\Support\Phone::format($inv->customer_phone) }}</span></td>
                        <td class="text-right font-semibold whitespace-nowrap">{{ number_format($inv->total, 0, ',', ' ') }}</td>
                        <td class="text-right whitespace-nowrap">{{ $inv->isQuote() ? '' : number_format($inv->balance(), 0, ',', ' ') }}</td>
                        <td><span class="chip {{ $inv->statusClass() }}">{{ $inv->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-ink-400">{{ $kind === 'quote' ? 'Aucun devis.' : 'Aucune facture.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.pro>
