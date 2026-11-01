@props(['product', 'showShop' => true])
@php($availability = $product->availability())
<article class="card flex flex-col overflow-hidden">
    <a href="{{ route('products.show', $product) }}" class="relative flex aspect-[4/3] items-center justify-center bg-ink-50 text-4xl">
        @if ($product->coverUrl())
            <img src="{{ $product->coverUrl() }}" alt="{{ $product->name }}" loading="lazy" class="size-full object-cover">
        @else
            <span aria-hidden="true">{{ $product->category->icon ?? '📦' }}</span>
        @endif
        <span class="absolute top-2 left-2 flex flex-col items-start gap-1">
            @if ($product->discountPercent())
                <span class="chip bg-red-600 text-white">-{{ $product->discountPercent() }} %</span>
            @endif
            @if ($product->condition === 'used')
                <span class="chip bg-ink-800 text-white">Occasion</span>
            @endif
        </span>
    </a>
    <div class="flex flex-1 flex-col p-3">
        <a href="{{ route('products.show', $product) }}" class="line-clamp-2 text-sm font-semibold hover:text-brand-600">{{ $product->name }}</a>
        @if ($product->relationLoaded('fitments') && $product->fitments->isNotEmpty())
            <p class="mt-0.5 line-clamp-1 text-xs text-ink-400">{{ $product->fitments->take(2)->map->label()->implode(', ') }}{{ $product->fitments->count() > 2 ? '…' : '' }}</p>
        @elseif ($product->compatibility)
            <p class="mt-0.5 line-clamp-1 text-xs text-ink-400">{{ $product->compatibility }}</p>
        @endif
        <p class="mt-1.5 text-base font-extrabold text-brand-600">{{ $product->priceLabel() }}<span class="text-xs font-semibold">{{ $product->unitSuffix() }}</span>
            @if ($product->discountPercent())
                <span class="text-xs font-normal text-ink-400 line-through">{{ \App\Models\Product::formatPrice($product->compare_at_price) }}</span>
            @endif
        </p>
        <span class="chip mt-1 w-fit {{ $availability['class'] }}">{{ $availability['label'] }}</span>
        @if ($showShop)
            <p class="mt-auto truncate pt-2 text-xs text-ink-500">
                @if ($product->getAttribute('distance_m') !== null)
                    <strong class="text-ink-700">📍 {{ $product->distance_m < 1000 ? round($product->distance_m / 10) * 10 .' m' : number_format($product->distance_m / 1000, 1, ',', ' ').' km' }}</strong> ·
                @endif
                {{ $product->provider->business_name }}
            </p>
        @endif
    </div>
</article>
