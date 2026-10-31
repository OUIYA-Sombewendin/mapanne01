<x-layouts.admin title="Avis">
    <h1 class="mb-1 text-2xl font-extrabold">Modération des avis</h1>
    <p class="mb-4 text-sm text-ink-500">
        Pré-modération : <strong>{{ feature('reviews_premoderation') ? 'activée' : 'désactivée' }}</strong>
        (<a href="{{ route('admin.settings.index', 'reviews') }}" class="text-brand-600">modifier</a>).
    </p>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'Tous', 'pending' => 'En attente ('.$pendingCount.')', 'low' => 'Notes ≤ 2', 'hidden' => 'Masqués'] as $key => $label)
            <a href="{{ route('admin.reviews.index', array_filter(['filter' => $key])) }}" @class([
                'chip px-3 py-1.5 text-sm',
                'bg-ink-800 text-white' => request('filter', '') === $key,
                'bg-white text-ink-600 ring-1 ring-ink-200' => request('filter', '') !== $key,
            ])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Prestataire</th><th>Client</th><th>Note</th><th>Commentaire</th><th>IP</th><th></th></tr></thead>
            <tbody>
                @forelse ($reviews as $r)
                    <tr @class(['opacity-50' => $r->is_hidden])>
                        <td class="whitespace-nowrap text-ink-500">{{ $r->created_at->format('d/m/Y') }}</td>
                        <td><a href="{{ route('providers.show', $r->provider) }}" class="text-brand-600">{{ $r->provider->business_name }}</a></td>
                        <td>{{ $r->user->name }}<br><span class="text-xs text-ink-400">{{ $r->user->email }}</span></td>
                        <td class="whitespace-nowrap text-amber-500">{{ str_repeat('★', $r->rating) }}</td>
                        <td class="max-w-md">{{ $r->comment }}
                            @if ($r->is_pending) <span class="chip bg-amber-100 text-amber-800">en attente</span> @endif
                        </td>
                        <td class="text-xs text-ink-400">{{ $r->ip_address }}</td>
                        <td>
                            <div class="flex gap-1">
                                @if ($r->is_pending)
                                    <form method="POST" action="{{ route('admin.reviews.approve', $r) }}">@csrf<button class="btn btn-primary btn-sm">Publier</button></form>
                                @endif
                                <form method="POST" action="{{ route('admin.reviews.toggle', $r) }}">@csrf
                                    <button class="btn btn-ghost btn-sm">{{ $r->is_hidden ? 'Republier' : 'Masquer' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-ink-400">Aucun avis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reviews->links() }}</div>
</x-layouts.admin>
