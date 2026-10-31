<x-layouts.app title="Mes demandes de pièces">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">Mes demandes de pièces</h1>
        <a href="{{ route('part-requests.create') }}" class="btn btn-primary">+ Nouvelle demande</a>
    </div>

    <div class="space-y-3">
        @forelse ($requests as $r)
            <a href="{{ route('part-requests.show', $r) }}" class="card flex items-center justify-between gap-3 p-4 hover:ring-brand-200">
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ $r->title }}</p>
                    <p class="text-xs text-ink-400">{{ $r->vehicle }} · {{ $r->city?->name }} · {{ $r->created_at->diffForHumans() }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-lg font-extrabold text-brand-600">{{ $r->offers_count }}</p>
                    <p class="text-xs text-ink-400">offre(s) · {{ $r->statusLabel() }}</p>
                </div>
            </a>
        @empty
            <div class="card p-8 text-center">
                <p class="text-4xl">🔎</p>
                <p class="mt-2 font-semibold">Vous n'avez pas encore de demande</p>
                <p class="mt-1 text-sm text-ink-500">Décrivez la pièce que vous ne trouvez pas : les boutiques vous répondent avec leur prix.</p>
                <a href="{{ route('part-requests.create') }}" class="btn btn-primary mt-4">Demander une pièce</a>
            </div>
        @endforelse
    </div>
</x-layouts.app>
