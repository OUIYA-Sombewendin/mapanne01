{{--
    Bouton de retour bien visible.
    <x-back :href="route('…')" label="Catalogue" />
    smart : revient à la page précédente du site si elle existe (garde les filtres de recherche), sinon href.
    dark : variante pour un bandeau sombre.
--}}
@props(['href', 'label' => 'Retour', 'smart' => false, 'dark' => false])
@php
    $target = $href;
    if ($smart) {
        $previous = url()->previous();
        $sameSite = parse_url($previous, PHP_URL_HOST) === request()->getHost();
        if ($sameSite && $previous !== url()->current() && ! str_contains($previous, '/connexion')) {
            $target = $previous;
        }
    }
@endphp
<a href="{{ $target }}" {{ $attributes->class([
    'group inline-flex items-center gap-2.5 rounded-full py-1.5 pr-4 pl-1.5 text-sm font-semibold transition',
    'bg-white text-ink-800 shadow-sm ring-1 ring-ink-200 hover:ring-brand-300 hover:text-brand-700' => ! $dark,
    'on-color bg-white/10 text-white ring-1 ring-white/25 hover:bg-white/20' => $dark,
]) }}>
    <span @class([
        'flex size-7 items-center justify-center rounded-full transition group-hover:-translate-x-0.5',
        'on-color bg-brand-500 text-white' => ! $dark,
        'bg-white text-ink-900' => $dark,
    ])>
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
    </span>
    {{ $label }}
</a>
