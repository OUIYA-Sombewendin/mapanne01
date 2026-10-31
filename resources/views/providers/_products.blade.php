            {{-- Catalogue de pièces (boutiques et garages) --}}
            @if ($products->isNotEmpty())
                <section class="card p-4 sm:p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-bold">Pièces en vente <span class="font-normal text-ink-400">({{ $productsCount }})</span></h2>
                        <a href="{{ route('products.index', ['shop' => $provider->slug]) }}" class="text-sm text-brand-600">Tout le catalogue →</a>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($products as $product)
                            <x-product-card :product="$product->setRelation('provider', $provider)" :show-shop="false" />
                        @endforeach
                    </div>
                </section>
            @endif
