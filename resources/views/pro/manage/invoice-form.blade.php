@php($isQuote = $kind === 'quote')
<x-layouts.app :title="$isQuote ? 'Nouveau devis' : 'Nouvelle facture'">
    <h1 class="mb-4 text-2xl font-extrabold">{{ $isQuote ? 'Nouveau devis' : 'Nouvelle vente / facture' }}</h1>
    @include('pro.manage._nav')

    @php($oldLines = old('lines', [['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => '']]))
    <form method="POST" action="{{ route('pro.manage.invoices.store') }}" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]"
          x-data="invoiceForm(@js($products), @js(array_values($oldLines)), {{ (int) old('discount', 0) }})">
        @csrf
        <input type="hidden" name="kind" value="{{ $kind }}">

        <section class="card space-y-4 p-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field name="customer_name" label="Client" placeholder="Nom (facultatif)" />
                <x-field name="customer_phone" type="tel" label="Téléphone" placeholder="70 00 00 00" />
                <x-field name="issued_at" type="date" label="Date" :value="now()->toDateString()" required />
            </div>

            <div>
                <h2 class="mb-2 font-bold">Articles et prestations</h2>
                @error('lines') <p class="field-error mb-2">{{ $message }}</p> @enderror
                @foreach ($errors->get('lines.*') as $messages) <p class="field-error">{{ $messages[0] }}</p> @endforeach

                <div class="space-y-2">
                    <template x-for="(line, i) in lines" :key="line.key">
                        <div class="grid grid-cols-12 items-end gap-2 rounded-xl bg-ink-50 p-2">
                            @if ($products->isNotEmpty())
                                <div class="col-span-12 sm:col-span-4">
                                    <label class="text-xs text-ink-500">Pièce du stock</label>
                                    <select class="input py-2" :name="`lines[${i}][product_id]`" x-model="line.product_id" @change="pick(line)">
                                        <option value="">— Prestation / autre —</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->stock }} en stock)</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="col-span-12 {{ $products->isNotEmpty() ? 'sm:col-span-3' : 'sm:col-span-6' }}">
                                <label class="text-xs text-ink-500">Désignation</label>
                                <input class="input py-2" :name="`lines[${i}][description]`" x-model="line.description" placeholder="Ex. Main d'œuvre vidange">
                            </div>
                            <div class="col-span-3 sm:col-span-1">
                                <label class="text-xs text-ink-500">Qté</label>
                                <input type="number" min="1" class="input px-2 py-2" :name="`lines[${i}][quantity]`" x-model.number="line.quantity" required>
                            </div>
                            <div class="col-span-5 sm:col-span-2">
                                <label class="text-xs text-ink-500">Prix unitaire</label>
                                <input type="number" min="0" class="input py-2" :name="`lines[${i}][unit_price]`" x-model.number="line.unit_price" required>
                            </div>
                            <div class="col-span-3 sm:col-span-1 pb-2 text-right text-sm font-semibold" x-text="fmt(line.quantity * line.unit_price)"></div>
                            <button type="button" class="col-span-1 pb-2 text-lg text-ink-400 hover:text-red-600" @click="remove(i)" aria-label="Retirer la ligne">×</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="add()" class="btn btn-ghost btn-sm mt-2">+ Ajouter une ligne</button>
            </div>

            <x-field name="notes" type="textarea" label="Notes (affichées sur le document)" rows="2" />
        </section>

        <aside class="card h-fit space-y-3 p-5 lg:sticky lg:top-20">
            <div class="flex justify-between text-sm"><span>Sous-total</span><strong x-text="fmt(subtotal())"></strong></div>
            <div>
                <label class="field-label" for="discount">Remise (FCFA)</label>
                <input id="discount" type="number" name="discount" min="0" x-model.number="discount" class="input">
            </div>
            <div class="flex justify-between border-t border-ink-100 pt-3 text-lg"><span>Total</span><strong x-text="fmt(total())"></strong></div>
            @unless ($isQuote)
                <div>
                    <label class="field-label" for="amount_paid">Montant encaissé</label>
                    <div class="flex gap-2">
                        <input id="amount_paid" type="number" name="amount_paid" min="0" x-ref="paid" value="{{ old('amount_paid') }}" class="input">
                        <button type="button" class="btn btn-ghost btn-sm" @click="$refs.paid.value = total()">Tout</button>
                    </div>
                </div>
                <div>
                    <label class="field-label" for="payment_method">Moyen de paiement</label>
                    <select id="payment_method" name="payment_method" class="input">
                        <option value="">—</option>
                        @foreach ($paymentMethods as $m) <option @selected(old('payment_method') === $m)>{{ $m }}</option> @endforeach
                    </select>
                </div>
                <p class="text-xs text-ink-400">Les pièces du stock sont retirées automatiquement.</p>
            @endunless
            <button class="btn btn-primary w-full">{{ $isQuote ? 'Enregistrer le devis' : 'Enregistrer la vente' }}</button>
        </aside>
    </form>

    <script>
        function invoiceForm(products, initial, discount) {
            let seq = 0;
            const blank = () => ({ key: ++seq, product_id: '', description: '', quantity: 1, unit_price: '' });
            return {
                lines: initial.map(l => ({ ...blank(), ...l, product_id: l.product_id ? String(l.product_id) : '' })),
                discount,
                add() { this.lines.push(blank()); },
                remove(i) { this.lines.splice(i, 1); if (!this.lines.length) this.add(); },
                pick(line) {
                    const p = products.find(p => String(p.id) === String(line.product_id));
                    if (p) { line.description = p.name; line.unit_price = p.price; }
                },
                subtotal() { return this.lines.reduce((s, l) => s + (Number(l.quantity) || 0) * (Number(l.unit_price) || 0), 0); },
                total() { return Math.max(0, this.subtotal() - (Number(this.discount) || 0)); },
                fmt(v) { return new Intl.NumberFormat('fr-FR').format(v || 0) + ' F'; },
            };
        }
    </script>
</x-layouts.app>
