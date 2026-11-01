{{--
    Pages de connexion, d'inscription et de mot de passe : épurées, sans le menu du site,
    pour que la personne se concentre sur le formulaire. Un lien « Retour au site » reste en haut.
--}}
@props(['title' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ \App\Support\Theme::brand() }}">
    <title>{{ $title ? $title.' · '.setting('site_name') : setting('site_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeCss) <style>{!! $themeCss !!}</style> @endif
</head>
<body class="flex min-h-dvh flex-col bg-ink-50">
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="Accueil {{ setting('site_name') }}">
            <x-logo class="size-9" />
            <span class="text-lg font-extrabold tracking-tight">Ma<span class="text-brand-500">Panne</span></span>
        </a>
        <a href="{{ route('home') }}" class="flex items-center gap-1 text-sm font-medium text-ink-500 hover:text-ink-800">
            <span aria-hidden="true">←</span> Retour au site
        </a>
    </header>

    @if (session('success') || session('error'))
        <div class="mx-auto w-full max-w-md px-4">
            <div @class([
                'mb-2 rounded-xl px-4 py-3 text-sm font-medium',
                'bg-emerald-600 text-white' => session('success'),
                'bg-red-600 text-white' => session('error'),
            ]) role="status">{{ session('success') ?? session('error') }}</div>
        </div>
    @endif

    <main @class(['mx-auto w-full flex-1 px-4 py-4 md:py-8', 'max-w-5xl' => $wide, 'max-w-md' => ! $wide])>
        {{ $slot }}
    </main>

    <footer class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-center gap-x-4 gap-y-1 px-4 py-6 text-xs text-ink-400">
        <span>© {{ date('Y') }} {{ setting('site_name') }}</span>
        @foreach ($footerPages ?? [] as $footerPage)
            <a href="{{ route('pages.show', $footerPage->slug) }}" class="hover:text-ink-700">{{ $footerPage->title }}</a>
        @endforeach
        <a href="{{ route('emergency') }}" class="text-red-600 hover:text-red-700">Numéros d'urgence</a>
    </footer>
</body>
</html>
