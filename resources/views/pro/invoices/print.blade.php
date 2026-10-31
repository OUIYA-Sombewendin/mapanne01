@php($f = fn ($v) => number_format($v, 0, ',', ' '))
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $invoice->title() }} · {{ $provider->business_name }}</title>
    @vite(['resources/css/app.css'])
    @if ($themeCss) <style>{!! $themeCss !!}</style> @endif
    <style>
        @page { size: A4; margin: 14mm; }
        @media print { .no-print { display: none !important; } body { background: #fff; } .sheet { box-shadow: none; margin: 0; padding: 0; } }
    </style>
</head>
<body class="bg-ink-100 text-ink-900">
    <div class="no-print mx-auto flex max-w-3xl justify-between gap-2 px-4 py-4">
        <a href="{{ route('pro.manage.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">← Retour</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm">🖨 Imprimer ou enregistrer en PDF</button>
    </div>

    <main class="sheet mx-auto mb-10 max-w-3xl bg-white p-8 shadow-sm sm:p-12">
        <header class="flex items-start justify-between gap-6 border-b-4 border-brand-500 pb-6">
            <div class="flex items-start gap-3">
                @if ($provider->logoUrl())
                    <img src="{{ $provider->logoUrl() }}" alt="" class="size-16 rounded-lg object-cover">
                @endif
                <div class="text-sm">
                    <p class="text-xl font-extrabold">{{ $provider->business_name }}</p>
                    @if ($provider->address || $provider->city) <p>{{ collect([$provider->address, $provider->city?->name])->filter()->implode(', ') }}</p> @endif
                    <p>Tél. {{ \App\Support\Phone::format($provider->phone) }}</p>
                    @if ($provider->rccm) <p>RCCM : {{ $provider->rccm }}</p> @endif
                    @if ($provider->ifu) <p>IFU : {{ $provider->ifu }}</p> @endif
                </div>
            </div>
            <div class="text-right">
                <p class="text-3xl font-extrabold tracking-tight text-brand-600 uppercase">{{ $invoice->isQuote() ? 'Devis' : 'Facture' }}</p>
                <p class="font-semibold">N° {{ $invoice->number }}</p>
                <p class="text-sm">Date : {{ $invoice->issued_at->format('d/m/Y') }}</p>
                @if ($invoice->isCancelled()) <p class="mt-1 font-bold text-red-600">ANNULÉ{{ $invoice->isQuote() ? '' : 'E' }}</p> @endif
            </div>
        </header>

        @if ($invoice->customer_name || $invoice->customer_phone)
            <section class="mt-6 text-sm">
                <p class="text-xs font-semibold tracking-wide text-ink-400 uppercase">Client</p>
                <p class="font-semibold">{{ $invoice->customer_name }}</p>
                @if ($invoice->customer_phone) <p>{{ \App\Support\Phone::format($invoice->customer_phone) }}</p> @endif
            </section>
        @endif

        <table class="mt-6 w-full text-sm">
            <thead>
                <tr class="border-b-2 border-ink-800 text-left text-xs tracking-wide uppercase">
                    <th class="py-2">Désignation</th><th class="py-2 text-right">Qté</th><th class="py-2 text-right">Prix unitaire</th><th class="py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr class="border-b border-ink-100">
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2 text-right">{{ $item->quantity }}</td>
                        <td class="py-2 text-right">{{ $f($item->unit_price) }}</td>
                        <td class="py-2 text-right">{{ $f($item->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 ml-auto w-64 space-y-1 text-sm">
            <div class="flex justify-between"><span>Sous-total</span><span>{{ $f($invoice->subtotal) }} FCFA</span></div>
            @if ($invoice->discount) <div class="flex justify-between"><span>Remise</span><span>− {{ $f($invoice->discount) }} FCFA</span></div> @endif
            <div class="flex justify-between border-t-2 border-ink-800 pt-1 text-base font-extrabold"><span>Total</span><span>{{ $f($invoice->total) }} FCFA</span></div>
            @unless ($invoice->isQuote())
                <div class="flex justify-between"><span>Payé</span><span>{{ $f($invoice->amount_paid) }} FCFA</span></div>
                <div class="flex justify-between font-semibold"><span>Reste à payer</span><span>{{ $f($invoice->balance()) }} FCFA</span></div>
            @endunless
        </div>

        @if ($invoice->notes) <p class="mt-8 text-sm whitespace-pre-line">{{ $invoice->notes }}</p> @endif
        @if ($invoice->isQuote()) <p class="mt-4 text-xs text-ink-500">Devis valable 30 jours.</p> @endif

        <footer class="mt-12 border-t border-ink-100 pt-3 text-center text-xs text-ink-400">
            {{ setting('invoice_footer') }}
        </footer>
    </main>
</body>
</html>
