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
            <a href="{{ route('pro.manage.invoices.index', request()->only(['q', 'payment', 'seller', 'from', 'to', 'sort', 'dir'])) }}" @class(['rounded-lg px-3 py-1.5', 'bg-white shadow-sm' => $kind === 'invoice', 'text-ink-500' => $kind !== 'invoice'])>Factures</a>
            <a href="{{ route('pro.manage.invoices.index', ['kind' => 'quote'] + request()->only(['q', 'payment', 'seller', 'from', 'to', 'sort', 'dir'])) }}" @class(['rounded-lg px-3 py-1.5', 'bg-white shadow-sm' => $kind === 'quote', 'text-ink-500' => $kind !== 'quote'])>Devis</a>
        </div>
    </div>
    <x-filter-bar :keys="['q', 'status', 'payment', 'seller', 'from', 'to']" :keep="['kind']">
            <input type="hidden" name="kind" value="{{ $kind }}">
            <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-48" placeholder="N°, client, téléphone">
            <select name="status" class="input-sm">
                <option value="">Tous les statuts</option>
                @foreach (\App\Models\Invoice::STATUSES as $key => [$label])
                    @if ($kind === 'quote' ? in_array($key, ['draft', 'accepted', 'cancelled']) : ! in_array($key, ['draft', 'accepted']))
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>
            <select name="payment" class="input-sm">
                <option value="">Tous les paiements</option>
                @foreach ($paymentMethods as $m) <option @selected(request('payment') === $m)>{{ $m }}</option> @endforeach
            </select>
            @if ($sellers->count() > 1)
                <select name="seller" class="input-sm">
                    <option value="">Tous les vendeurs</option>
                    @foreach ($sellers as $s) <option value="{{ $s->id }}" @selected(request('seller') == $s->id)>{{ $s->name }}</option> @endforeach
                </select>
            @endif
            <label class="flex items-center gap-1 text-sm text-ink-500">Du <input type="date" name="from" value="{{ request('from') }}" class="input-sm"></label>
            <label class="flex items-center gap-1 text-sm text-ink-500">au <input type="date" name="to" value="{{ request('to') }}" class="input-sm"></label>
    </x-filter-bar>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><x-sort-th column="number" label="N°" /><x-sort-th column="date" label="Date" /><x-sort-th column="customer" label="Client" /><x-sort-th column="total" label="Total" align="right" /><x-sort-th column="balance" label="Reste dû" align="right" /><x-sort-th column="status" label="Statut" /></tr></thead>
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
