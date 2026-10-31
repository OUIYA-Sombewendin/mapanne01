{{-- Affichage public de la fiche métier. --}}
@props(['provider'])
@php
    $details = $provider->details();
    $shown = $details?->exists ? $details->present() : null;
@endphp
@if ($shown && ($shown['chips'] || $shown['facts']))
    <div {{ $attributes }}>
        @if ($shown['chips'])
            <div class="flex flex-wrap gap-1.5">
                @foreach ($shown['chips'] as $chip) <span class="chip bg-ink-50 text-ink-700 ring-1 ring-ink-100">{{ $chip }}</span> @endforeach
            </div>
        @endif
        @if ($shown['facts'])
            <ul class="mt-2 space-y-0.5 text-sm text-ink-600">
                @foreach ($shown['facts'] as $fact) <li>{{ $fact }}</li> @endforeach
            </ul>
        @endif
    </div>
@endif
