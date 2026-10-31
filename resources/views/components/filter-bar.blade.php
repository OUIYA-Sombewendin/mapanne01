{{--
    Barre de filtres commune à toutes les listes : appliqués dès qu'on choisit (pas de bouton « Filtrer »),
    texte appliqué après une courte pause, compteur de filtres actifs, réinitialisation, tri conservé.
    <x-filter-bar :keys="['q', 'status', 'city']"> …champs… </x-filter-bar>
    keys : noms des paramètres de filtre (pour le compteur et la réinitialisation).
    keep : paramètres à garder lors de la réinitialisation (ex. un onglet de statut).
--}}
@props(['keys' => [], 'keep' => [], 'action' => null])
@php
    $active = collect($keys)->filter(fn ($k) => filled(request()->query($k)))->count();
    $resetParams = array_filter(request()->only(array_merge($keep, ['sort', 'dir'])), 'filled');
@endphp
<form method="GET" @if ($action) action="{{ $action }}" @endif x-data="autoFilter" {{ $attributes->class(['card mb-4 p-3']) }}>
    {{-- Tri et paramètres conservés --}}
    @foreach (array_filter(request()->only(array_merge($keep, ['sort', 'dir'])), 'filled') as $name => $value)
        @unless (in_array($name, $keys, true))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endunless
    @endforeach
    <div class="flex flex-wrap items-center gap-2">
        <span class="flex items-center gap-1.5 pr-1 text-sm font-semibold text-ink-600">
            <x-icon name="search" class="size-4 text-ink-400" /> Filtres
            @if ($active) <span class="on-color rounded-full bg-brand-500 px-1.5 text-xs text-white">{{ $active }}</span> @endif
        </span>
        {{ $slot }}
        @if ($active)
            <a href="{{ url()->current().($resetParams ? '?'.http_build_query($resetParams) : '') }}" class="ml-auto text-sm font-medium text-brand-600 hover:underline">✕ Réinitialiser</a>
        @endif
    </div>
    <noscript><button class="btn btn-dark btn-sm mt-2">Filtrer</button></noscript>
</form>
