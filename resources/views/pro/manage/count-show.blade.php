@php
    $money = fn ($v) => \App\Support\ShopReport::money($v);
    $codes = fn ($p) => collect([$p->barcode, $p->reference])->filter()->map(fn ($c) => mb_strtoupper(str_replace(' ', '', $c)))->implode(' ');
@endphp
<x-layouts.pro :title="'Inventaire #'.$count->id">
    <div class="mb-4"><x-back :href="route('pro.manage.counts')" label="Inventaires" /></div>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-extrabold">Inventaire #{{ $count->id }}</h1>
        <span class="chip {{ $count->statusClass() }}">{{ $count->statusLabel() }}</span>
        <span class="text-sm text-ink-500">{{ $count->scope }} · commencé le {{ $count->created_at->format('d/m/Y à H:i') }}</span>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-ink-500">Pièces comptées</p><p class="text-2xl font-extrabold">{{ $summary['counted'] }} / {{ $summary['total'] }}</p></div>
        <div class="card p-4"><p class="text-xs text-ink-500">{{ $count->isOpen() ? 'Écarts trouvés' : 'Pièces corrigées' }}</p><p @class(['text-2xl font-extrabold', 'text-amber-600' => $summary['gaps']])>{{ $count->isOpen() ? $summary['gaps'] : $count->adjusted_count }}</p></div>
        <div class="card p-4"><p class="text-xs text-ink-500">Unités manquantes</p><p @class(['text-2xl font-extrabold', 'text-red-700' => $summary['missing']])>{{ $count->isOpen() ? $summary['missing'] : '—' }}</p></div>
        <div class="card p-4"><p class="text-xs text-ink-500">Écart valorisé (prix d'achat)</p><p @class(['text-2xl font-extrabold', 'text-red-700' => ($count->isOpen() ? $summary['value'] : $count->gap_value) < 0])>{{ $money($count->isOpen() ? $summary['value'] : $count->gap_value) }}</p></div>
    </div>

    @if ($count->isOpen() && $summary['moved'])
        <p class="mb-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">⚠️ {{ $summary['moved'] }} pièce(s) ont été vendues ou reçues depuis le début du comptage (stock marqué ⚠️). Vérifiez leur quantité avant de valider : c'est la quantité comptée qui sera retenue.</p>
    @endif

    @if ($count->isOpen())
        <div x-data="{
                code: '', message: '',
                add(raw) {
                    const code = String(raw || '').replace(/\s/g, '').toUpperCase();
                    if (!code) return;
                    const input = [...this.$root.querySelectorAll('[data-codes]')].find((el) => el.dataset.codes.split(' ').includes(code));
                    if (!input) { this.message = 'Code inconnu dans cet inventaire : ' + code; this.code = ''; return; }
                    input.value = (parseInt(input.value || '0', 10) || 0) + 1;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.scrollIntoView({ block: 'center' });
                    this.message = '+1 : ' + input.dataset.name + ' (' + input.value + ')';
                    this.code = '';
                },
             }">
            {{-- Comptage au scanner (douchette, caméra) ou par référence --}}
            <div class="card mb-4 flex flex-wrap items-center gap-2 p-4" x-data="barcodeScanner" @scanned="add($event.detail)">
                <input x-model="code" @keydown.enter.prevent="add(code)" class="input max-w-xs" placeholder="Scannez ou tapez un code-barres / une référence" autocomplete="off" aria-label="Code-barres ou référence">
                <button type="button" @click="add(code)" class="btn btn-dark">+1</button>
                <button type="button" x-show="supported" x-cloak @click="start()" class="btn btn-ghost">📷 Scanner</button>
                <p class="w-full text-sm text-ink-500" x-text="message || 'Chaque scan ajoute 1 à la quantité comptée. Vous pouvez aussi taper les quantités dans le tableau.'"></p>
                @include('pro._scanner')
            </div>

            <form method="POST" action="{{ route('pro.manage.counts.update', $count) }}">
                @csrf @method('PUT')
                <div class="card overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Pièce</th><th>Empl.</th><th class="text-right">Stock système</th><th class="w-32">Compté</th><th class="text-right">Écart</th></tr></thead>
                        <tbody>
                            @foreach ($lines as $line)
                                @php($p = $line->product)
                                <tr x-data="{ v: @js($line->counted === null ? '' : (string) $line->counted) }" :class="v !== '' && Number(v) !== {{ $p->stock }} && 'bg-amber-50'">
                                    <td><span class="font-semibold">{{ $p->name }}</span>
                                        <br><span class="text-xs text-ink-400">{{ collect([$p->reference, $p->barcode])->filter()->implode(' · ') }}</span></td>
                                    <td class="text-ink-500">{{ $p->shelf ?? '—' }}</td>
                                    <td class="text-right">{{ $p->stock }} @if ($p->stock !== $line->expected) <span title="Stock au début du comptage : {{ $line->expected }}">⚠️</span> @endif</td>
                                    <td><input type="number" min="0" name="counted[{{ $line->id }}]" x-model="v" data-codes="{{ $codes($p) }}" data-name="{{ $p->name }}" class="input-sm w-24" inputmode="numeric"></td>
                                    <td class="text-right font-semibold"><span x-text="v === '' ? '—' : (Number(v) - {{ $p->stock }} > 0 ? '+' : '') + (Number(v) - {{ $p->stock }})" :class="v !== '' && Number(v) < {{ $p->stock }} ? 'text-red-700' : ''"></span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('counted.*') <p class="field-error mt-2">{{ $message }}</p> @enderror
                <div class="mt-4 flex flex-wrap gap-2">
                    <button class="btn btn-primary">Enregistrer le comptage</button>
                    <p class="self-center text-xs text-ink-500">Vous pouvez enregistrer et revenir plus tard. Les pièces laissées vides ne seront pas modifiées.</p>
                </div>
            </form>
        </div>

        <div class="mt-6 flex flex-wrap gap-2 border-t border-ink-100 pt-4">
            <form method="POST" action="{{ route('pro.manage.counts.apply', $count) }}" onsubmit="return confirm('Valider l\'inventaire ? Le stock des pièces comptées sera remplacé par les quantités saisies. Pensez à enregistrer le comptage avant.')">
                @csrf
                <button class="btn btn-dark">✓ Valider l'inventaire et corriger le stock</button>
            </form>
            <form method="POST" action="{{ route('pro.manage.counts.destroy', $count) }}" onsubmit="return confirm('Annuler cet inventaire ? Le stock ne sera pas modifié.')">
                @csrf @method('DELETE')
                <button class="btn btn-ghost text-red-700">Annuler l'inventaire</button>
            </form>
        </div>
    @else
        <div class="card overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Pièce</th><th>Empl.</th><th class="text-right">Stock au début</th><th class="text-right">Compté</th><th class="text-right">Écart</th></tr></thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td class="font-semibold">{{ $line->product->name }}</td>
                            <td class="text-ink-500">{{ $line->product->shelf ?? '—' }}</td>
                            <td class="text-right">{{ $line->expected }}</td>
                            <td class="text-right">{{ $line->counted ?? '—' }}</td>
                            <td @class(['text-right font-semibold', 'text-red-700' => $line->gap() < 0, 'text-emerald-700' => $line->gap() > 0])>{{ $line->gap() === null ? '—' : ($line->gap() > 0 ? '+' : '').$line->gap() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($count->status === 'applied')
            <p class="mt-3 text-sm text-ink-500">Validé le {{ $count->applied_at->format('d/m/Y à H:i') }}. Les corrections figurent dans <a href="{{ route('pro.manage.stock.movements', ['reason' => 'inventory']) }}" class="text-brand-600 underline">l'historique des mouvements</a>.</p>
        @endif
    @endif
</x-layouts.pro>
