{{--
    Menu latéral commun (admin et espace pro). La première rubrique est affichée à plat ;
    les suivantes sont des groupes repliables sur fond teinté, ouverts d'office s'ils contiennent la page active.
    sections : [titre => [[url, actif, icône, libellé, compteur], …]] ; icons : [titre => icône du groupe].
--}}
@props(['sections', 'icons' => []])
<nav class="flex-1 space-y-2 overflow-y-auto overscroll-contain px-4 py-5" x-init="followActive($el)" @scroll.debounce.150ms="rememberScroll($el)">
    @foreach ($sections as $section => $links)
        @php
            $hasActive = collect($links)->contains(fn ($l) => $l[1]);
            $total = collect($links)->sum(fn ($l) => (int) ($l[4] ?? 0));
            $slug = \Illuminate\Support\Str::slug($section);
        @endphp
        @if ($loop->first)
            <div class="space-y-1 pb-2">
                @foreach ($links as [$url, $active, $icon, $label, $count])
                    @include('components.side-nav-link')
                @endforeach
            </div>
        @else
            <div x-data="{ open: {{ $hasActive ? 'true' : 'false' }} }" x-init="if (!open) { try { open = localStorage.getItem('mp-nav-{{ $slug }}') === '1' } catch (e) {} }">
                <button type="button" @click="open = !open; try { localStorage.setItem('mp-nav-{{ $slug }}', open ? '1' : '0') } catch (e) {}"
                        class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-left text-[15px] font-bold transition"
                        :class="[open || {{ $hasActive ? 'true' : 'false' }} ? 'bg-accent-50 text-accent-700' : 'text-ink-700 hover:bg-ink-50', collapsed && 'lg:hidden']">
                    <x-icon :name="$icons[$section] ?? 'layers'" class="size-5 text-accent-600" />
                    <span class="flex-1 truncate">{{ $section }}</span>
                    @if ($total)
                        <span class="on-color rounded-full bg-brand-500 px-2 text-xs font-bold text-white" x-show="!open">{{ $total }}</span>
                    @endif
                    <x-icon name="chevron" class="size-4 text-ink-400 transition" ::class="open ? 'rotate-0' : '-rotate-90'" />
                </button>
                <div x-show="open || collapsed" class="mt-1 space-y-1 border-l-2 border-accent-100 pl-2" :class="collapsed ? 'lg:ml-0 lg:border-0 lg:pl-0' : 'ml-5'">
                    @foreach ($links as [$url, $active, $icon, $label, $count])
                        @include('components.side-nav-link')
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
    {{ $slot }}
</nav>
