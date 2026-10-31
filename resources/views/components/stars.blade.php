@props(['rating' => 0, 'count' => null, 'size' => 'text-sm'])
<span {{ $attributes->class(['inline-flex items-center gap-1', $size]) }}>
    @if ($count === 0)
        <span class="text-ink-400">Pas encore d'avis</span>
    @else
        <span class="text-amber-500" aria-hidden="true">★</span>
        <span class="font-semibold">{{ number_format($rating, 1, ',', '') }}</span>
        @if ($count !== null)
            <span class="text-ink-400">({{ $count }})</span>
        @endif
    @endif
</span>
