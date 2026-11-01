<x-layouts.app title="Mes dépannages">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">📞 Mes dépannages</h1>
            <p class="text-sm text-ink-500">L'historique de vos demandes d'aide et le pro qui est intervenu.</p>
        </div>
        <a href="{{ route('assist.create') }}" class="btn bg-red-600 text-white hover:bg-red-700">🚨 Je suis en panne</a>
    </div>

    <div class="space-y-3">
        @forelse ($assists as $assist)
            <a href="{{ route('assist.show', $assist) }}" class="card flex items-center gap-4 p-4 transition hover:shadow-md">
                <span class="text-3xl">{{ $assist->problemIcon() }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold">{{ $assist->problemLabel() }} <span class="font-normal text-ink-500">· {{ $assist->vehicleLabel() }}</span></span>
                    <span class="block text-xs text-ink-500">{{ $assist->created_at->format('d/m/Y à H:i') }}{{ $assist->provider ? ' · '.$assist->provider->business_name : '' }}</span>
                </span>
                <span class="chip shrink-0 {{ $assist->statusClass() }}">{{ $assist->statusLabel() }}</span>
            </a>
        @empty
            <div class="card p-8 text-center">
                <p class="text-4xl">🛟</p>
                <p class="mt-2 font-semibold">Aucune demande de dépannage</p>
                <p class="mt-1 text-sm text-ink-500">En cas de panne, un appui suffit : les pros disponibles autour de vous sont prévenus.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-4">{{ $assists->links() }}</div>
</x-layouts.app>
