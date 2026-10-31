<x-layouts.app :title="$page->title">
    <article class="card mx-auto max-w-3xl p-6 sm:p-8">
        <h1 class="mb-4 text-2xl font-extrabold">{{ $page->title }}</h1>
        <div class="prose-mp">{!! $page->html() !!}</div>
        <p class="mt-8 text-xs text-ink-400">Dernière mise à jour : {{ $page->updated_at?->translatedFormat('d F Y') }}</p>
    </article>
</x-layouts.app>
