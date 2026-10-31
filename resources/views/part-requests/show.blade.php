<x-layouts.app :title="$partRequest->title">
    <div class="mb-4"><x-back :href="route('part-requests.index')" label="Mes demandes" /></div>

    <div class="mt-3 grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section class="space-y-3">
            <h1 class="text-2xl font-extrabold">Offres reçues ({{ $partRequest->offers->count() }})</h1>
            @forelse ($partRequest->offers as $offer)
                @php($shop = $offer->provider)
                <article class="card flex flex-wrap items-center gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('providers.show', $shop) }}" class="font-semibold hover:text-brand-600">{{ $shop->business_name }}</a>
                        <p class="text-xs text-ink-400">{{ $shop->neighborhood?->name }} · {{ \App\Models\Product::CONDITIONS[$offer->condition] ?? '' }} · {{ $offer->created_at->diffForHumans() }}</p>
                        @if ($offer->message) <p class="mt-1 text-sm text-ink-700">« {{ $offer->message }} »</p> @endif
                        @if ($offer->product && $offer->product->isVisible())
                            <a href="{{ route('products.show', $offer->product) }}" class="text-xs text-brand-600">Voir la pièce proposée →</a>
                        @endif
                    </div>
                    <p class="text-xl font-extrabold text-brand-600">{{ $offer->priceLabel() }}</p>
                    <div class="flex w-full gap-2 sm:w-auto">
                        <a href="{{ $shop->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm flex-1">WhatsApp</a>
                        <a href="{{ $shop->callUrl() }}" class="btn btn-primary btn-sm flex-1">📞 Appeler</a>
                    </div>
                </article>
            @empty
                <div class="card p-8 text-center text-sm text-ink-500">
                    Pas encore d'offre. Les boutiques de {{ $partRequest->city?->name }} ont été prévenues de votre demande : revenez un peu plus tard{{ auth()->user()->email ? ', vous recevrez un email à chaque offre' : '' }}.
                </div>
            @endforelse
        </section>

        <aside class="card h-fit space-y-2 p-5 text-sm">
            <p class="text-lg font-bold">{{ $partRequest->title }}</p>
            <span class="chip {{ $partRequest->isOpen() ? 'bg-emerald-50 text-emerald-700' : 'bg-ink-100 text-ink-500' }}">{{ $partRequest->statusLabel() }}</span>
            @if ($partRequest->photoUrl()) <img src="{{ $partRequest->photoUrl() }}" alt="" class="mt-2 w-full rounded-xl"> @endif
            <p><span class="text-ink-400">Véhicule :</span> {{ $partRequest->vehicle ?: '—' }} {{ $partRequest->vehicleTypeLabel() ? '('.$partRequest->vehicleTypeLabel().')' : '' }}</p>
            <p><span class="text-ink-400">Catégorie :</span> {{ $partRequest->category?->name ?? '—' }}</p>
            <p><span class="text-ink-400">Ville :</span> {{ $partRequest->city?->name }}</p>
            @if ($partRequest->details) <p class="text-ink-700">{{ $partRequest->details }}</p> @endif
            <p class="text-xs text-ink-400">Expire {{ $partRequest->expires_at?->diffForHumans() }}</p>
            @if ($partRequest->isOpen() && $partRequest->user_id === auth()->id())
                <form method="POST" action="{{ route('part-requests.close', $partRequest) }}" class="pt-2">@csrf
                    <button class="btn btn-ghost btn-sm w-full">J'ai trouvé, clôturer la demande</button>
                </form>
            @endif
        </aside>
    </div>
</x-layouts.app>
