{{-- Logo : image choisie dans Admin › Apparence, sinon épingle de carte + clé plate aux couleurs du thème. --}}
@if (filled(setting('site_logo')))
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url(setting('site_logo')) }}" alt="" {{ $attributes->merge(['class' => 'object-contain']) }}>
@else
    <svg {{ $attributes }} viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <path d="M16 2C9.9 2 5 6.8 5 12.8 5 20.6 16 30 16 30s11-9.4 11-17.2C27 6.8 22.1 2 16 2z" fill="var(--color-brand-500)"/>
        <path d="M19.8 8.4a3.6 3.6 0 00-4.6 4.4l-4.3 4.3a1.3 1.3 0 001.8 1.8l4.3-4.3a3.6 3.6 0 004.4-4.6l-2 2-1.8-.4-.4-1.8z" fill="#fff"/>
    </svg>
@endif
