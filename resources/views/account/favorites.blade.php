<x-layouts.app title="Mes favoris">
    <div class="mb-5">
        <h1 class="text-2xl font-extrabold">❤️ Mes favoris</h1>
        <p class="text-sm text-ink-500">Vos pros de confiance, à appeler en un geste.</p>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        @forelse ($favorites as $fav)
            <div class="card flex items-center gap-3 p-4">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-ink-50 text-2xl">{{ $fav->type->icon() }}</span>
                <a href="{{ route('providers.show', $fav) }}" class="min-w-0 flex-1">
                    <span class="block truncate font-semibold hover:text-brand-600">{{ $fav->business_name }}</span>
                    <span class="block truncate text-xs text-ink-500">{{ $fav->type->label() }} · {{ collect([$fav->neighborhood?->name, $fav->city?->name])->filter()->implode(', ') }}</span>
                </a>
                <a href="{{ $fav->callUrl() }}" class="btn btn-primary btn-sm shrink-0">Appeler</a>
                <form method="POST" action="{{ route('favorites.toggle', $fav) }}">@csrf
                    <button class="text-xl text-red-500 hover:text-red-700" title="Retirer des favoris" aria-label="Retirer {{ $fav->business_name }} des favoris">♥</button>
                </form>
            </div>
        @empty
            <div class="card p-8 text-center md:col-span-2">
                <p class="text-4xl">❤️</p>
                <p class="mt-2 font-semibold">Aucun favori pour l'instant</p>
                <p class="mt-1 text-sm text-ink-500">Touchez ❤️ sur la fiche d'un mécanicien, d'un dépanneur ou d'une station pour le retrouver ici.</p>
                <a href="{{ route('search') }}" class="btn btn-primary mt-4">Trouver un pro</a>
            </div>
        @endforelse
    </div>
</x-layouts.app>
