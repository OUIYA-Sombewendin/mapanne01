{{--
    En-tête de colonne triable : un clic trie, un second clic inverse l'ordre. Les filtres sont conservés.
    <x-sort-th column="name" label="Nom" />   (align="right" pour les montants)
--}}
@props(['column', 'label', 'align' => 'left'])
@php
    $current = request()->query('sort') === $column;
    $dir = $current && request()->query('dir') === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $dir, 'page' => null]);
@endphp
<th {{ $attributes->class(['text-right' => $align === 'right', 'text-center' => $align === 'center']) }} @if ($current) aria-sort="{{ request()->query('dir') === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ $url }}" @class(['inline-flex items-center gap-1 hover:text-ink-800', 'text-ink-800' => $current])>
        {{ $label }}
        <span @class(['text-[10px]', 'text-brand-600' => $current, 'text-ink-300' => ! $current])>{{ $current ? (request()->query('dir') === 'asc' ? '▲' : '▼') : '⇅' }}</span>
    </a>
</th>
