{{-- En-tête de section de formulaire : icône, titre, explication courte. --}}
<div class="mb-5 flex items-start gap-3">
    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-5" /></span>
    <div>
        <h2 class="text-lg font-bold">{{ $title }}</h2>
        @if ($text) <p class="text-sm text-ink-500">{{ $text }}</p> @endif
    </div>
</div>
