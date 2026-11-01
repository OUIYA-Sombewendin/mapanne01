@props(['title' => null, 'description' => null, 'wide' => false, 'bare' => false])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (\App\Support\Channels::pushReady()) <meta name="vapid-key" content="{{ config('webpush.vapid.public_key') }}"> @endif
    <meta name="theme-color" content="{{ \App\Support\Theme::brand() }}">
    <title>{{ $title ? $title.' · '.setting('site_name') : setting('site_name').' — '.setting('site_tagline') }}</title>
    <meta name="description" content="{{ $description ?? 'Trouvez vite un mécanicien, un garage, un dépanneur ou une station-service vérifiés près de vous au Burkina Faso.' }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeCss) <style>{!! $themeCss !!}</style> @endif
</head>
<body class="min-h-dvh pb-20 md:pb-0">
    {{-- Fond de l'en-tête et du pied de page : réglable dans Admin › Apparence (clair, sombre, couleur principale) --}}
    <header class="site-bar bar-{{ setting('theme_header_style', 'dark') }} sticky top-0 z-30 border-b border-ink-100 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="Accueil MaPanne">
                <x-logo class="size-8" />
                <span class="text-lg font-extrabold tracking-tight">Ma<span class="text-brand-500">Panne</span></span>
            </a>

            <nav class="ml-6 hidden items-center gap-1 text-sm font-medium md:flex">
                <a href="{{ route('search') }}" @class(['rounded-lg px-3 py-2 hover:bg-ink-50', 'text-brand-600' => request()->routeIs('search')])>Trouver un pro</a>
                @if (feature('marketplace_enabled'))
                    <a href="{{ route('products.index') }}" @class(['rounded-lg px-3 py-2 hover:bg-ink-50', 'text-brand-600' => request()->routeIs('products.*', 'part-requests.*')])>Pièces détachées</a>
                @endif
                <a href="{{ route('search', ['type' => 'station']) }}" class="rounded-lg px-3 py-2 hover:bg-ink-50">Stations</a>
                <a href="{{ route('emergency') }}" class="rounded-lg px-3 py-2 text-red-600 hover:bg-red-50">Urgence</a>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                @auth
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-full p-1 pr-3 hover:bg-ink-50" aria-label="Menu du compte">
                            <span class="flex size-8 items-center justify-center rounded-full bg-ink-800 text-xs font-bold text-white">{{ auth()->user()->initials() }}</span>
                            <span class="hidden text-sm font-medium sm:block">{{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}</span>
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-ink-100">
                            <div class="border-b border-ink-50 px-4 py-2 text-xs text-ink-400">{{ auth()->user()->role->label() }}</div>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 hover:bg-ink-50">Administration</a>
                            @elseif (auth()->user()->isProvider())
                                <a href="{{ route('pro.dashboard') }}" class="block px-4 py-2 hover:bg-ink-50">Mon espace pro</a>
                            @endif
                            <a href="{{ route('account.show') }}" class="block px-4 py-2 hover:bg-ink-50">Mon compte</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50">Se déconnecter</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Connexion</a>
                    <a href="{{ route('register', ['type' => 'pro']) }}" class="btn btn-dark btn-sm hidden sm:inline-flex">Je suis un pro</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Un admin consulte ce compte (assistance) : bandeau permanent pour revenir à son compte --}}
    @if (session()->has(\App\Http\Controllers\Admin\ImpersonationController::SESSION_KEY))
        <div class="sticky top-14 z-30 flex flex-wrap items-center justify-center gap-3 bg-amber-400 px-4 py-2 text-sm font-medium text-amber-950">
            <span>👁 Vous êtes dans le compte de <strong>{{ auth()->user()->name }}</strong> (accès administrateur).</span>
            <form method="POST" action="{{ route('impersonation.stop') }}">@csrf
                <button class="rounded-lg bg-amber-950 px-3 py-1 text-xs font-bold text-white">Revenir à mon compte admin</button>
            </form>
        </div>
    @endif

    {{-- Bannière d'annonce, pilotée depuis Réglages › Général --}}
    @if (feature('announcement_enabled') && filled(setting('announcement_text')))
        <div class="bg-ink-800 px-4 py-2 text-center text-sm text-white">{{ setting('announcement_text') }}</div>
    @endif
    @auth
        @if (! auth()->user()->hasVerifiedEmail() && ! request()->routeIs('verification.*'))
            <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-xs text-amber-800">
                Confirmez votre email {{ auth()->user()->email }}.
                <form method="POST" action="{{ route('verification.send') }}" class="inline">@csrf<button class="font-semibold underline">Renvoyer le lien</button></form>
            </div>
        @endif
    @endauth

    @if (session('success') || session('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition
             class="fixed inset-x-4 top-16 z-40 mx-auto max-w-md">
            <div @class([
                'flex items-start gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-lg',
                'bg-emerald-600 text-white' => session('success'),
                'bg-red-600 text-white' => session('error'),
            ])>
                <span class="flex-1">{{ session('success') ?? session('error') }}</span>
                <button @click="show = false" class="opacity-80 hover:opacity-100" aria-label="Fermer">✕</button>
            </div>
        </div>
    @endif

    <main @class(['mx-auto px-4 py-5 md:py-8', 'max-w-6xl' => ! $wide, 'max-w-7xl' => $wide, '!p-0 !max-w-none' => $bare])>
        {{ $slot }}
    </main>

    <footer class="site-bar bar-{{ setting('theme_footer_style', 'dark') }} mt-10 border-t border-ink-100 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-6 text-sm text-ink-400">
            <p>© {{ date('Y') }} {{ setting('site_name') }} · {{ setting('support_email') }}</p>
            <p class="flex flex-wrap gap-4">
                @foreach ($footerPages ?? [] as $footerPage)
                    <a href="{{ route('pages.show', $footerPage->slug) }}" class="hover:text-ink-700">{{ $footerPage->title }}</a>
                @endforeach
                <a href="{{ route('register', ['type' => 'pro']) }}" class="hover:text-ink-700">Inscrire mon activité</a>
                <a href="{{ route('emergency') }}" class="hover:text-ink-700">Numéros d'urgence</a>
            </p>
        </div>
    </footer>

    {{-- Barre de navigation du bas (mobile) : les actions clés à portée de pouce. --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-100 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
        <div class="grid grid-cols-5 text-[11px] font-medium text-ink-500">
            <a href="{{ route('home') }}" @class(['flex flex-col items-center gap-0.5 py-2', 'text-brand-600' => request()->routeIs('home')])>
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 11l9-7 9 7v9a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1z"/></svg>
                Accueil
            </a>
            <a href="{{ route('search') }}" @class(['flex flex-col items-center gap-0.5 py-2', 'text-brand-600' => request()->routeIs('search', 'providers.show')])>
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                Rechercher
            </a>
            <a href="{{ feature('marketplace_enabled') ? route('products.index') : route('search', ['type' => 'station']) }}" @class(['flex flex-col items-center gap-0.5 py-2', 'text-brand-600' => request()->routeIs('products.*', 'part-requests.*')])>
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M4.9 4.9l2.1 2.1m10 10l2.1 2.1M2 12h3m14 0h3M4.9 19.1l2.1-2.1m10-10l2.1-2.1"/></svg>
                {{ feature('marketplace_enabled') ? 'Pièces' : 'Stations' }}
            </a>
            <a href="{{ route('emergency') }}" @class(['flex flex-col items-center gap-0.5 py-2 text-red-600', 'font-bold' => request()->routeIs('emergency')])>
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3l9 16H3z"/><path d="M12 10v4m0 3h.01"/></svg>
                Urgence
            </a>
            <a href="{{ auth()->check() ? auth()->user()->homeRoute() : route('login') }}" @class(['flex flex-col items-center gap-0.5 py-2', 'text-brand-600' => request()->routeIs('account.*', 'pro.*', 'admin.*', 'login', 'register')])>
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>
                {{ auth()->check() ? 'Mon espace' : 'Compte' }}
            </a>
        </div>
    </nav>
</body>
</html>
