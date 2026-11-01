@php($money = fn ($v) => \App\Support\ShopReport::money($v))
<x-layouts.pro title="Inventaire physique">
    <div class="mb-4"><x-back :href="route('pro.manage.stock')" label="Stock" /></div>
    <h1 class="mb-1 text-2xl font-extrabold">📋 Inventaire physique</h1>
    <p class="mb-5 max-w-3xl text-sm text-ink-500">Comptez ce qui est vraiment sur vos rayons. Vous saisissez les quantités (à la main ou en scannant les codes-barres), vous voyez les écarts, puis vous validez : le stock est corrigé et chaque correction reste dans l'historique des mouvements.</p>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <section class="card overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>N°</th><th>Date</th><th>Périmètre</th><th class="text-right">Comptées</th><th class="text-right">Corrections</th><th class="text-right">Écart valorisé</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse ($counts as $c)
                        <tr>
                            <td><a href="{{ route('pro.manage.counts.show', $c) }}" class="font-semibold text-brand-600">#{{ $c->id }}</a></td>
                            <td class="whitespace-nowrap text-ink-500">{{ $c->created_at->format('d/m/Y') }}<br><span class="text-xs">{{ $c->user?->name }}</span></td>
                            <td>{{ $c->scope }}</td>
                            <td class="text-right">{{ $c->counted_count }} / {{ $c->lines_count }}</td>
                            <td class="text-right">{{ $c->status === 'applied' ? $c->adjusted_count : '—' }}</td>
                            <td @class(['text-right whitespace-nowrap', 'text-red-700' => $c->gap_value < 0, 'text-emerald-700' => $c->gap_value > 0])>{{ $c->status === 'applied' ? $money($c->gap_value) : '—' }}</td>
                            <td><span class="chip {{ $c->statusClass() }}">{{ $c->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-ink-400">Aucun inventaire pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $counts->links() }}</div>
        </section>

        <aside>
            @if ($open)
                <div class="card space-y-3 p-5">
                    <h2 class="font-bold">Inventaire en cours</h2>
                    <p class="text-sm text-ink-500">#{{ $open->id }} · {{ $open->scope }} · commencé le {{ $open->created_at->format('d/m à H:i') }}</p>
                    <a href="{{ route('pro.manage.counts.show', $open) }}" class="btn btn-primary w-full">Continuer le comptage</a>
                </div>
            @else
                <form method="POST" action="{{ route('pro.manage.counts.store') }}" class="card space-y-3 p-5" x-data="{ scope: @js(old('scope', 'all')) }">
                    @csrf
                    <h2 class="font-bold">Nouvel inventaire</h2>
                    <p class="text-sm text-ink-500">Astuce : comptez un rayon à la fois, quand la boutique est calme.</p>
                    @foreach (['all' => 'Toute la boutique ('.$total.' pièces)', 'category' => 'Une catégorie', 'shelf' => 'Un emplacement (rayon)'] as $value => $label)
                        @continue($value === 'shelf' && $shelves->isEmpty())
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="scope" value="{{ $value }}" x-model="scope"> {{ $label }}</label>
                    @endforeach
                    <select name="category" class="input" x-show="scope === 'category'" x-cloak>
                        <option value="">Choisir…</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->icon }} {{ $c->name }}</option>
                            @foreach ($c->children as $sub) <option value="{{ $sub->id }}">&nbsp;&nbsp;› {{ $sub->name }}</option> @endforeach
                        @endforeach
                    </select>
                    @error('category') <p class="field-error">{{ $message }}</p> @enderror
                    @if ($shelves->isNotEmpty())
                        <select name="shelf" class="input" x-show="scope === 'shelf'" x-cloak>
                            <option value="">Choisir…</option>
                            @foreach ($shelves as $s) <option>{{ $s }}</option> @endforeach
                        </select>
                        @error('shelf') <p class="field-error">{{ $message }}</p> @enderror
                    @endif
                    <button class="btn btn-primary w-full">Commencer le comptage</button>
                </form>
            @endif
        </aside>
    </div>
</x-layouts.pro>
