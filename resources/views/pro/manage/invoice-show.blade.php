@php($f = fn ($v) => number_format($v, 0, ',', ' '))
<x-layouts.pro :title="$invoice->title()">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <a href="{{ route('pro.manage.invoices.index', $invoice->isQuote() ? ['kind' => 'quote'] : []) }}" class="text-sm text-ink-500">← {{ $invoice->isQuote() ? 'Devis' : 'Factures' }}</a>
            <h1 class="text-2xl font-extrabold">{{ $invoice->title() }} <span class="chip align-middle {{ $invoice->statusClass() }}">{{ $invoice->statusLabel() }}</span></h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('pro.manage.invoices.print', $invoice) }}" target="_blank" class="btn btn-dark btn-sm">🖨 Imprimer / PDF</a>
            @if ($invoice->customer_phone)
                <a href="{{ \App\Support\Phone::whatsappUrl($invoice->customer_phone, 'Bonjour, voici votre '.mb_strtolower($invoice->title()).' de '.$provider->business_name.' : '.$f($invoice->total).' FCFA.') }}" target="_blank" class="btn btn-whatsapp btn-sm">WhatsApp</a>
            @endif
            @if ($invoice->isQuote() && $invoice->status === 'draft')
                <form method="POST" action="{{ route('pro.manage.invoices.convert', $invoice) }}">@csrf<button class="btn btn-primary btn-sm">Transformer en facture</button></form>
            @endif
            @unless ($invoice->isCancelled())
                <form method="POST" action="{{ route('pro.manage.invoices.cancel', $invoice) }}" onsubmit="return confirm('Annuler ce document ?{{ $invoice->isQuote() ? '' : ' Les pièces seront remises en stock.' }}')">@csrf<button class="btn btn-ghost btn-sm text-red-600">Annuler</button></form>
            @endunless
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section class="card p-5">
            <div class="mb-4 grid gap-1 text-sm sm:grid-cols-2">
                <p><span class="text-ink-500">Client :</span> {{ $invoice->customer_name ?: '—' }} {{ $invoice->customer_phone ? '· '.\App\Support\Phone::format($invoice->customer_phone) : '' }}</p>
                <p class="sm:text-right"><span class="text-ink-500">Date :</span> {{ $invoice->issued_at->format('d/m/Y') }}@if ($invoice->seller) · <span class="text-ink-500">par</span> {{ $invoice->seller->name }}@endif</p>
                @if ($invoice->convertedFrom) <p><span class="text-ink-500">Issue du devis</span> <a class="text-brand-600" href="{{ route('pro.manage.invoices.show', $invoice->convertedFrom) }}">{{ $invoice->convertedFrom->number }}</a></p> @endif
                @if ($converted) <p><span class="text-ink-500">Facturé :</span> <a class="text-brand-600" href="{{ route('pro.manage.invoices.show', $converted) }}">{{ $converted->number }}</a></p> @endif
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Désignation</th><th class="text-right">Qté</th><th class="text-right">P.U.</th><th class="text-right">Total</th><th class="text-right">Marge</th></tr></thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right">{{ $f($item->unit_price) }}</td>
                                <td class="text-right font-semibold">{{ $f($item->total) }}</td>
                                <td class="text-right text-ink-500">{{ $item->unit_cost ? $f($item->total - $item->quantity * $item->unit_cost) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($invoice->notes) <p class="mt-4 rounded-xl bg-ink-50 p-3 text-sm">{{ $invoice->notes }}</p> @endif
            <p class="mt-2 text-xs text-ink-400">La colonne « Marge » n'apparaît pas sur le document imprimé.</p>
        </section>

        <aside class="space-y-4">
            <div class="card space-y-2 p-5 text-sm">
                <div class="flex justify-between"><span>Sous-total</span><span>{{ $f($invoice->subtotal) }} F</span></div>
                @if ($invoice->discount) <div class="flex justify-between"><span>Remise</span><span>− {{ $f($invoice->discount) }} F</span></div> @endif
                <div class="flex justify-between border-t border-ink-100 pt-2 text-lg font-bold"><span>Total</span><span>{{ $f($invoice->total) }} F</span></div>
                @unless ($invoice->isQuote())
                    <div class="flex justify-between"><span>Encaissé</span><span>{{ $f($invoice->amount_paid) }} F</span></div>
                    <div class="flex justify-between font-semibold"><span>Reste dû</span><span @class(['text-red-600' => $invoice->balance() > 0])>{{ $f($invoice->balance()) }} F</span></div>
                    @if ($invoice->payment_method) <p class="text-ink-500">Paiement : {{ $invoice->payment_method }}</p> @endif
                @endunless
            </div>

            @if (! $invoice->isQuote() && ! $invoice->isCancelled() && $invoice->balance() > 0)
                <form method="POST" action="{{ route('pro.manage.invoices.payment', $invoice) }}" class="card space-y-3 p-5">
                    @csrf
                    <h2 class="font-bold">Enregistrer un paiement</h2>
                    <x-field name="amount" type="number" label="Montant reçu (FCFA)" :value="$invoice->balance()" min="1" :max="$invoice->balance()" required />
                    <select name="payment_method" class="input">
                        <option value="">Moyen de paiement</option>
                        @foreach ($paymentMethods as $m) <option>{{ $m }}</option> @endforeach
                    </select>
                    <button class="btn btn-primary w-full">Enregistrer</button>
                </form>
            @endif
        </aside>
    </div>
</x-layouts.pro>
