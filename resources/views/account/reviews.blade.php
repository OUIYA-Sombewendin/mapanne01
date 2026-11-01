<x-layouts.app title="Mes avis">
    <div class="mb-5">
        <h1 class="text-2xl font-extrabold">★ Mes avis</h1>
        <p class="text-sm text-ink-500">Les avis que vous avez laissés aux pros. Vous pouvez les modifier depuis leur fiche.</p>
    </div>

    <div class="space-y-3">
        @forelse ($reviews as $review)
            <div class="card p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('providers.show', $review->provider) }}" class="font-semibold hover:text-brand-600">{{ $review->provider->business_name }}</a>
                    <span class="text-amber-500">{{ str_repeat('★', $review->rating) }}<span class="text-ink-200">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                    <span class="text-xs text-ink-400">{{ $review->created_at->format('d/m/Y') }}</span>
                    @if ($review->is_hidden) <span class="chip bg-ink-100 text-ink-500">masqué par la modération</span> @endif
                </div>
                @if (filled($review->comment)) <p class="mt-1 text-sm text-ink-600">{{ $review->comment }}</p> @endif
            </div>
        @empty
            <div class="card p-8 text-center">
                <p class="text-4xl">⭐</p>
                <p class="mt-2 font-semibold">Vous n'avez pas encore donné d'avis</p>
                <p class="mt-1 text-sm text-ink-500">Après une réparation, notez le pro sur sa fiche : cela aide les autres conducteurs.</p>
            </div>
        @endforelse
    </div>
</x-layouts.app>
