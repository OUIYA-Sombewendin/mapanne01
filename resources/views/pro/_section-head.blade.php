{{-- En-tête de section de carte (cartes en p-6) : bandeau teinté, trait orange, icône et titre en couleur d'accent. --}}
<div class="relative -mx-6 -mt-6 mb-5 flex items-start gap-3 rounded-t-2xl border-b px-6 py-4" style="background: var(--section-bg); border-color: var(--tint)">
    <span class="absolute top-3 bottom-3 left-0 w-1 rounded-r bg-brand-500"></span>
    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-accent-600 shadow-sm ring-1 ring-accent-100"><x-icon :name="$icon" class="size-5" /></span>
    <div>
        <h2 class="text-lg font-extrabold" style="color: var(--heading)">{{ $title }}</h2>
        @if ($text) <p class="text-sm text-ink-500">{{ $text }}</p> @endif
    </div>
</div>
