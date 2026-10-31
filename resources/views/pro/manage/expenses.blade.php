@php($f = fn ($v) => number_format($v, 0, ',', ' '))
<x-layouts.pro title="Dépenses">
    <h1 class="mb-4 text-2xl font-extrabold">Dépenses</h1>
    @include('pro.manage._period')

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
            <section class="card p-5">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="font-bold">Par catégorie</h2>
                    <p class="text-lg font-extrabold">{{ $f(array_sum($byCategory)) }} F</p>
                </div>
                @php($max = max(1, max($byCategory ?: [0])))
                <ul class="space-y-2 text-sm">
                    @forelse ($byCategory as $cat => $total)
                        <li>
                            <a href="{{ request()->fullUrlWithQuery(['category' => $cat]) }}" class="flex justify-between hover:text-brand-600"><span>{{ $cat }}</span><span class="font-semibold">{{ $f($total) }} F</span></a>
                            <div class="mt-1 h-1.5 rounded-full bg-ink-100"><div class="h-full rounded-full bg-ink-700" style="width: {{ $total / $max * 100 }}%"></div></div>
                        </li>
                    @empty
                        <li class="text-ink-400">Aucune dépense sur la période.</li>
                    @endforelse
                </ul>
            </section>

            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Catégorie</th><th>Description</th><th class="text-right">Montant</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($expenses as $e)
                            <tr>
                                <td class="whitespace-nowrap">{{ $e->spent_at->format('d/m/Y') }}</td>
                                <td>{{ $e->category }}</td>
                                <td class="text-ink-500">{{ $e->description }} @if ($e->payment_method) <span class="text-xs">· {{ $e->payment_method }}</span> @endif</td>
                                <td class="text-right font-semibold whitespace-nowrap">{{ $f($e->amount) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('pro.manage.expenses.destroy', $e) }}" onsubmit="return confirm('Supprimer cette dépense ?')">
                                        @csrf @method('DELETE')<button class="text-xs text-ink-400 hover:text-red-600">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-ink-400">Aucune dépense.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $expenses->links() }}
        </div>

        <form id="ajout" method="POST" action="{{ route('pro.manage.expenses.store') }}" class="card h-fit space-y-3 p-5 lg:sticky lg:top-20">
            @csrf
            <h2 class="font-bold">Ajouter une dépense</h2>
            <x-field name="spent_at" type="date" label="Date" :value="now()->toDateString()" required />
            <div>
                <label class="field-label" for="category">Catégorie</label>
                <select id="category" name="category" class="input" required>
                    @foreach ($categories as $c) <option @selected(old('category') === $c)>{{ $c }}</option> @endforeach
                </select>
                @error('category') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <x-field name="amount" type="number" label="Montant (FCFA)" min="1" required />
            <x-field name="description" label="Description" placeholder="Ex. loyer de mars" />
            <select name="payment_method" class="input">
                <option value="">Moyen de paiement</option>
                @foreach ($paymentMethods as $m) <option>{{ $m }}</option> @endforeach
            </select>
            <button class="btn btn-primary w-full">Enregistrer</button>
            <p class="text-xs text-ink-400">Les achats de pièces se saisissent dans Stock › Entrée : ils comptent comme coût au moment de la vente.</p>
        </form>
    </div>
</x-layouts.pro>
