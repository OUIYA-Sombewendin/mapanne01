<x-layouts.pro title="Demandes de pièces">
    <h1 class="mb-1 text-2xl font-extrabold">Demandes de pièces</h1>
    <p class="mb-4 text-sm text-ink-500">Des clients de {{ $provider->city?->name ?? 'votre ville' }} cherchent une pièce. Faites-leur une offre : ils reçoivent votre prix et vous contactent.</p>

    <div class="space-y-3">
        @forelse ($requests as $r)
            @php($myOffer = $r->offers->first())
            <article class="card p-4" x-data="{ open: false }">
                <div class="flex flex-wrap items-start gap-3">
                    @if ($r->photoUrl())
                        <a href="{{ $r->photoUrl() }}" target="_blank"><img src="{{ $r->photoUrl() }}" alt="" class="size-20 rounded-xl object-cover"></a>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $r->category?->icon }} {{ $r->title }}</p>
                        <p class="text-xs text-ink-400">
                            {{ $r->vehicle ?: $r->vehicleTypeLabel() ?: 'Véhicule non précisé' }} · {{ $r->city?->name }} ·
                            {{ $r->created_at->diffForHumans() }} · {{ $r->offers_count }} offre(s)
                        </p>
                        @if ($r->details) <p class="mt-1 text-sm text-ink-700">{{ $r->details }}</p> @endif
                        @if ($myOffer)
                            <p class="mt-2 text-sm text-emerald-700">✓ Votre offre : {{ $myOffer->priceLabel() }}</p>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        @if ($r->whatsappUrlFor($provider))
                            <a href="{{ $r->whatsappUrlFor($provider) }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">WhatsApp</a>
                        @endif
                        <button @click="open = !open" class="btn btn-primary btn-sm">{{ $myOffer ? 'Modifier mon offre' : 'Faire une offre' }}</button>
                    </div>
                </div>

                <form x-cloak x-show="open" method="POST" action="{{ route('pro.part-requests.offer', $r) }}" class="mt-3 grid gap-3 rounded-xl bg-ink-50 p-3 sm:grid-cols-4">
                    @csrf
                    <label class="text-xs text-ink-500">Prix (FCFA)
                        <input type="number" name="price" min="50" value="{{ $myOffer?->price }}" class="input" required>
                    </label>
                    <label class="text-xs text-ink-500">État
                        <select name="condition" class="input">
                            <option value="new" @selected($myOffer?->condition === 'new')>Neuf</option>
                            @if (feature('allow_used_parts'))
                                <option value="used" @selected($myOffer?->condition === 'used')>Occasion</option>
                            @endif
                        </select>
                    </label>
                    <label class="text-xs text-ink-500 sm:col-span-2">Pièce de mon catalogue (facultatif)
                        <select name="product_id" class="input">
                            <option value="">—</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected($myOffer?->product_id === $p->id)>{{ $p->name }} ({{ $p->priceLabel() }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs text-ink-500 sm:col-span-3">Message
                        <input name="message" maxlength="500" value="{{ $myOffer?->message }}" class="input" placeholder="Ex. Disponible tout de suite, pose possible à l'atelier">
                    </label>
                    <button class="btn btn-primary self-end">Envoyer</button>
                </form>
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-ink-500">Aucune demande ouverte dans votre ville pour le moment.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.pro>
