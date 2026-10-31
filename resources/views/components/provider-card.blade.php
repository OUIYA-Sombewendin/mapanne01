@props(['provider', 'now' => now()])
@php($open = $provider->isOpenAt($now))
<article class="card flex gap-3 p-3 sm:p-4">
    <a href="{{ route('providers.show', $provider) }}" class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand-50 text-3xl sm:size-20">
        @if ($provider->logoUrl())
            <img src="{{ $provider->logoUrl() }}" alt="" loading="lazy" class="size-full object-cover">
        @else
            <span aria-hidden="true">{{ $provider->type->icon() }}</span>
        @endif
    </a>

    <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <a href="{{ route('providers.show', $provider) }}" class="block truncate font-semibold hover:text-brand-600">{{ $provider->business_name }}</a>
                <p class="truncate text-xs text-ink-400">
                    @if ($provider->distanceLabel()) <span class="font-semibold text-ink-700">{{ $provider->distanceLabel() }}</span> · @endif
                    {{ $provider->type->shortLabel() }}
                    @if ($provider->neighborhood) · {{ $provider->neighborhood->name }} @endif
                </p>
            </div>
            <x-stars :rating="$provider->rating_avg" :count="$provider->reviews_count" size="text-xs" class="shrink-0" />
        </div>

        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
            <span @class(['chip', 'bg-emerald-50 text-emerald-700' => $open, 'bg-ink-100 text-ink-500' => ! $open])>
                <span @class(['size-1.5 rounded-full', 'bg-emerald-500' => $open, 'bg-ink-400' => ! $open])></span>
                {{ $open ? 'Ouvert' : 'Fermé' }}
            </span>
            @if ($open && $provider->availability !== \App\Enums\Availability::Available)
                <span class="chip bg-amber-50 text-amber-700">{{ $provider->availability->label() }}</span>
            @endif
            @foreach (array_slice($provider->badges(), 1, 2) as $badge)
                <span class="chip {{ $badge['class'] }}">{{ $badge['label'] }}</span>
            @endforeach
        </div>

        @if ($provider->services->isNotEmpty())
            <p class="mt-1.5 line-clamp-1 text-xs text-ink-500">{{ $provider->services->pluck('name')->take(4)->implode(' · ') }}</p>
        @endif

        <div class="mt-2.5 flex gap-2">
            <a href="{{ $provider->callUrl() }}" class="btn btn-primary btn-sm flex-1 sm:flex-none">📞 Appeler</a>
            <a href="{{ $provider->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm flex-1 sm:flex-none">WhatsApp</a>
        </div>
    </div>
</article>
