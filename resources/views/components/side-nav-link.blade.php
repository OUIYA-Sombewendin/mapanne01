{{-- Lien du menu latéral (variables : $url, $active, $icon, $label, $count). --}}
<a href="{{ $url }}" title="{{ $label }}" @if ($active) data-active aria-current="page" @endif @class([
    'relative flex items-center gap-3.5 rounded-xl px-3.5 py-2.5 text-[15px] transition',
    'on-color bg-gradient-to-r from-accent-600 to-accent-500 font-semibold text-white shadow-md shadow-accent-800/20' => $active,
    'text-ink-700 hover:bg-accent-50 hover:text-accent-700' => ! $active,
]) :class="collapsed && 'lg:justify-center lg:px-0'">
    @if (str_starts_with($icon, 'emoji:'))
        <span class="flex size-5 shrink-0 items-center justify-center text-base">{{ substr($icon, 6) }}</span>
    @else
        <x-icon :name="$icon" :class="$active ? 'size-5' : 'size-5 text-ink-500'" />
    @endif
    <span class="flex-1 truncate" :class="collapsed && 'lg:hidden'">{{ $label }}</span>
    @if ($count)
        <span class="on-color rounded-full bg-brand-500 px-2 text-xs font-bold text-white" :class="collapsed && 'lg:absolute lg:top-0.5 lg:right-1.5 lg:px-1.5'">{{ $count }}</span>
    @endif
</a>
