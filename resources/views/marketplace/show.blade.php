<x-layouts.app :title="$product->name" :description="$product->name.' — '.$product->priceLabel().' chez '.$shop->business_name">
    @php($availability = $product->availability())

    @if ($isPreview)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>Aperçu privé.</strong> Ce produit n'est pas visible par le public
            @if ($product->is_hidden) (masqué par MaPanne : {{ $product->hidden_reason }}) @elseif (! $product->is_active) (retiré de la vente) @else (boutique non validée) @endif.
        </div>
    @endif

    <div class="mb-4"><x-back :href="route('products.index', ['category' => $product->category->slug])" label="Retour" smart /></div>

    <div class="mt-3 grid gap-5 pb-16 lg:grid-cols-[minmax(0,1fr)_360px] lg:pb-0">
        <div class="space-y-5">
            <section class="card overflow-hidden" x-data="{ current: 0 }">
                @if ($product->photos->isNotEmpty())
                    <div class="relative aspect-[4/3] bg-ink-50">
                        @foreach ($product->photos as $i => $photo)
                            <img x-show="current === {{ $i }}" @if ($i) x-cloak @endif src="{{ $photo->url() }}" alt="{{ $product->name }}" class="absolute inset-0 size-full object-contain">
                        @endforeach
                    </div>
                    @if ($product->photos->count() > 1)
                        <div class="flex gap-2 p-3">
                            @foreach ($product->photos as $i => $photo)
                                <button @click="current = {{ $i }}" :class="current === {{ $i }} ? 'ring-2 ring-brand-500' : 'opacity-70'" class="size-14 overflow-hidden rounded-lg">
                                    <img src="{{ $photo->url() }}" alt="" loading="lazy" class="size-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="flex h-40 items-center justify-center bg-ink-50 text-6xl sm:h-64">{{ $product->category->icon ?? '📦' }}</div>
                @endif
                {{-- Sur mobile, le nom et le prix suivent directement la photo. --}}
                <div class="p-4 lg:hidden">
                    <p class="text-lg font-extrabold">{{ $product->name }}</p>
                    <p class="text-2xl font-extrabold text-brand-600">{{ $product->priceLabel() }}
                        @if ($product->discountPercent())
                            <span class="text-sm font-normal text-ink-400 line-through">{{ \App\Models\Product::formatPrice($product->compare_at_price) }}</span>
                            <span class="chip bg-red-600 align-middle text-white">-{{ $product->discountPercent() }} %</span>
                        @endif
                    </p>
                    <span class="chip mt-1 {{ $availability['class'] }}">{{ $availability['label'] }}</span>
                    <p class="mt-2 text-sm text-ink-500">🏪 {{ $shop->business_name }} · {{ $shop->neighborhood?->name }}</p>
                </div>
            </section>

            {{-- Barre de contact fixe (mobile), au-dessus de la navigation du bas. --}}
            <div class="fixed inset-x-0 bottom-[calc(3.85rem+env(safe-area-inset-bottom))] z-20 grid grid-cols-2 gap-2 border-t border-ink-100 bg-white p-3 lg:hidden" x-data
                 @click="if ($event.target.closest('a[data-contact]')) navigator.sendBeacon(@js(route('products.contact', $product)), new URLSearchParams({ _token: document.querySelector('meta[name=csrf-token]').content }))">
                <a data-contact href="{{ $product->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-whatsapp">WhatsApp</a>
                <a data-contact href="tel:{{ $shop->phone }}" class="btn btn-primary">📞 Appeler</a>
            </div>

            <section class="card p-5">
                <h2 class="mb-3 font-bold">Caractéristiques</h2>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach (array_filter([
                        'Catégorie' => $product->category->name,
                        'État' => $product->conditionLabel(),
                        'Marque' => $product->brand,
                        'Référence' => $product->reference,
                        'Véhicules' => implode(', ', $product->vehicleTypeLabels()),
                        'Compatible avec' => $product->compatibility,
                        'Garantie' => $product->warranty,
                    ]) as $label => $value)
                        <div><dt class="text-xs text-ink-400">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($product->description)
                    <p class="mt-4 text-sm leading-relaxed whitespace-pre-line text-ink-700">{{ $product->description }}</p>
                @endif
                <p class="mt-4 rounded-xl bg-ink-50 p-3 text-xs text-ink-500">
                    💡 Vérifiez la compatibilité avec votre véhicule (marque, modèle, année) auprès du vendeur avant d'acheter.
                    Le paiement se fait directement avec la boutique.
                </p>
            </section>

            @if ($similar->isNotEmpty())
                <section>
                    <h2 class="mb-3 font-bold">Autres boutiques qui proposent une pièce similaire</h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($similar as $item)
                            <x-product-card :product="$item" />
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-5">
            <section class="card p-5 lg:sticky lg:top-20">
                <h1 class="text-xl font-extrabold">{{ $product->name }}</h1>
                <p class="mt-2 text-3xl font-extrabold text-brand-600">{{ $product->priceLabel() }}</p>
                @if ($product->discountPercent())
                    <p class="text-sm"><span class="text-ink-400 line-through">{{ \App\Models\Product::formatPrice($product->compare_at_price) }}</span>
                        <span class="chip bg-red-600 text-white">-{{ $product->discountPercent() }} %</span></p>
                @endif
                @if ($priceWatch)
                    <div class="mt-3 rounded-xl bg-accent-50 px-3 py-2 text-sm text-accent-900">
                        <p class="font-semibold">Prix constaté : {{ \App\Models\Product::formatPrice($priceWatch->min) }} – {{ \App\Models\Product::formatPrice($priceWatch->max) }}</p>
                        <p class="text-xs text-accent-800">Pour une pièce proche, dans {{ $priceWatch->shops }} boutiques sur {{ setting('site_name') }}.</p>
                    </div>
                @endif
                <span class="chip mt-2 {{ $availability['class'] }}">{{ $availability['label'] }}</span>

                <div class="mt-4 grid grid-cols-2 gap-2" x-data
                     @click="if ($event.target.closest('a[data-contact]')) navigator.sendBeacon(@js(route('products.contact', $product)), new URLSearchParams({ _token: document.querySelector('meta[name=csrf-token]').content }))">
                    <a data-contact href="{{ $product->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-whatsapp py-3">WhatsApp</a>
                    <a data-contact href="tel:{{ $shop->phone }}" class="btn btn-primary py-3">📞 Appeler</a>
                </div>
                <p class="mt-2 text-center text-xs text-ink-400">Le message WhatsApp mentionne la pièce et le prix.</p>

                <div class="mt-5 border-t border-ink-100 pt-4">
                    <p class="text-xs text-ink-400">Vendu par</p>
                    <a href="{{ route('providers.show', $shop) }}" class="mt-1 flex items-center gap-3">
                        <span class="flex size-12 items-center justify-center overflow-hidden rounded-xl bg-brand-50 text-2xl">
                            @if ($shop->logoUrl()) <img src="{{ $shop->logoUrl() }}" alt="" class="size-full object-cover"> @else {{ $shop->type->icon() }} @endif
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate font-semibold hover:text-brand-600">{{ $shop->business_name }}</span>
                            <span class="block text-xs text-ink-500">{{ $shop->neighborhood?->name }}{{ $shop->city ? ', '.$shop->city->name : '' }}</span>
                            <x-stars :rating="$shop->rating_avg" :count="$shop->reviews_count" size="text-xs" />
                        </span>
                    </a>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @php($open = $shop->isOpenAt(now()))
                        <span @class(['chip', 'bg-emerald-50 text-emerald-700' => $open, 'bg-ink-100 text-ink-500' => ! $open])>{{ $open ? 'Ouvert maintenant' : 'Fermé actuellement' }}</span>
                        @if ($shop->business_verified_at) <span class="chip bg-emerald-100 text-emerald-800">🏢 Entreprise vérifiée</span> @endif
                    </div>
                    <ul class="mt-3 space-y-1 text-sm text-ink-600">
                        @if (feature('delivery_enabled'))
                            <li>{{ $shop->delivery_available ? '🛵 Livraison possible'.($shop->delivery_details ? ' — '.$shop->delivery_details : '') : '🏪 Retrait en boutique' }}</li>
                        @endif
                        @if (! empty($shop->payment_methods))
                            <li>💳 {{ implode(' · ', $shop->payment_methods) }}</li>
                        @endif
                    </ul>
                    @if ($shop->directionsUrl())
                        <a href="{{ $shop->directionsUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm mt-3 w-full">🧭 Itinéraire vers la boutique</a>
                    @endif
                    <a href="https://wa.me/?text={{ rawurlencode($product->name.' — '.$product->priceLabel().' sur '.setting('site_name').' : '.route('products.show', $product)) }}"
                       target="_blank" rel="noopener" class="btn btn-ghost btn-sm mt-2 w-full">↗ Partager cette pièce</a>
                </div>
            </section>

            @if ($moreFromShop->isNotEmpty())
                <section class="card p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-sm font-bold">Dans la même boutique</h2>
                        <a href="{{ route('products.index', ['shop' => $shop->slug]) }}" class="text-xs text-brand-600">Tout voir</a>
                    </div>
                    <ul class="divide-y divide-ink-50">
                        @foreach ($moreFromShop as $item)
                            <li><a href="{{ route('products.show', $item) }}" class="flex items-center justify-between gap-2 py-2 text-sm hover:text-brand-600">
                                <span class="truncate">{{ $item->name }}</span><span class="shrink-0 font-semibold">{{ $item->priceLabel() }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @auth
                @unless ($isPreview || auth()->id() === $shop->user_id)
                    <details class="card p-4 text-sm">
                        <summary class="cursor-pointer text-ink-500">⚠️ Signaler cette annonce</summary>
                        <form method="POST" action="{{ route('providers.report', $shop) }}" class="mt-3 space-y-2">
                            @csrf
                            <select name="reason" class="input" required>
                                <option value="">Motif…</option>
                                @foreach ($reportReasons as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <textarea name="details" rows="2" class="input" placeholder="Détails">Annonce : {{ $product->name }}</textarea>
                            <button class="btn btn-ghost btn-sm w-full">Envoyer</button>
                        </form>
                    </details>
                @endunless
            @endauth
        </aside>
    </div>
</x-layouts.app>
