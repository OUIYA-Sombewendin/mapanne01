<x-layouts.admin title="Recherche">
    <h1 class="mb-4 text-2xl font-extrabold">Recherche</h1>
    <form class="mb-5 flex gap-2 sm:hidden">
        <input name="q" value="{{ $q }}" class="input" placeholder="Nom, email, téléphone, pièce…" autofocus>
        <button class="btn btn-dark">OK</button>
    </form>

    @if (mb_strlen($q) < 2)
        <p class="text-ink-500">Tapez au moins 2 caractères dans la barre de recherche.</p>
    @elseif ($providers->isEmpty() && $users->isEmpty() && $products->isEmpty())
        <p class="text-ink-500">Aucun résultat pour « {{ $q }} ».</p>
    @else
        <div class="grid gap-5 xl:grid-cols-3">
            @if ($providers->isNotEmpty())
                <section class="card p-5">
                    <h2 class="mb-2 font-bold">Prestataires</h2>
                    <ul class="divide-y divide-ink-50 text-sm">
                        @foreach ($providers as $p)
                            <li class="py-2"><a href="{{ route('admin.providers.show', $p) }}" class="font-semibold hover:text-brand-600">{{ $p->type->icon() }} {{ $p->business_name }}</a>
                                <span class="block text-xs text-ink-400">{{ $p->city?->name }} · {{ $p->status->label() }} · {{ \App\Support\Phone::format($p->phone) }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
            @if ($users->isNotEmpty())
                <section class="card p-5">
                    <h2 class="mb-2 font-bold">Utilisateurs</h2>
                    <ul class="divide-y divide-ink-50 text-sm">
                        @foreach ($users as $u)
                            <li class="py-2"><a href="{{ route('admin.users.index', ['q' => $u->email]) }}" class="font-semibold hover:text-brand-600">{{ $u->name }}</a>
                                <span class="block text-xs text-ink-400">{{ $u->email }} · {{ $u->role->label() }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
            @if ($products->isNotEmpty())
                <section class="card p-5">
                    <h2 class="mb-2 font-bold">Pièces</h2>
                    <ul class="divide-y divide-ink-50 text-sm">
                        @foreach ($products as $product)
                            <li class="py-2"><a href="{{ route('admin.marketplace.index', ['q' => $product->name]) }}" class="font-semibold hover:text-brand-600">{{ $product->name }}</a>
                                <span class="block text-xs text-ink-400">{{ $product->provider?->business_name }} · {{ number_format($product->price, 0, ',', ' ') }} F</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    @endif
</x-layouts.admin>
