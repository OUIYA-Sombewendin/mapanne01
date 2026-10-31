<x-layouts.admin title="Avis">
    <h1 class="mb-1 text-2xl font-extrabold">Modération des avis</h1>
    <p class="mb-4 text-sm text-ink-500">
        Pré-modération : <strong>{{ feature('reviews_premoderation') ? 'activée' : 'désactivée' }}</strong>
        (<a href="{{ route('admin.settings.index', 'reviews') }}" class="text-brand-600">modifier</a>).
    </p>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'Tous', 'pending' => 'En attente ('.$pendingCount.')', 'low' => 'Notes ≤ 2', 'hidden' => 'Masqués'] as $key => $label)
            <a href="{{ route('admin.reviews.index', array_filter(['filter' => $key] + request()->only(['q', 'rating', 'comment', 'from', 'sort', 'dir']))) }}" @class([
                'chip px-3 py-1.5 text-sm',
                'bg-ink-800 text-white' => request('filter', '') === $key,
                'bg-white text-ink-600 ring-1 ring-ink-200' => request('filter', '') !== $key,
            ])>{{ $label }}</a>
        @endforeach
    </div>

    <x-filter-bar :keys="['q', 'rating', 'comment', 'from']" :keep="['filter']">
        <input type="hidden" name="filter" value="{{ request('filter') }}">
        <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-56" placeholder="Prestataire, client, commentaire">
        <select name="rating" class="input-sm">
            <option value="">Toutes les notes</option>
            @for ($i = 5; $i >= 1; $i--) <option value="{{ $i }}" @selected(request('rating') == $i)>{{ str_repeat('★', $i) }}</option> @endfor
        </select>
        <select name="comment" class="input-sm">
            <option value="">Avec ou sans commentaire</option>
            <option value="1" @selected(request('comment') === '1')>Avec commentaire</option>
        </select>
        <label class="flex items-center gap-1 text-sm text-ink-500">Depuis <input type="date" name="from" value="{{ request('from') }}" class="input-sm"></label>
    </x-filter-bar>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr>
                <x-sort-th column="date" label="Date" />
                <x-sort-th column="provider" label="Prestataire" />
                <th>Client</th>
                <x-sort-th column="rating" label="Note" />
                <th>Commentaire</th><th>IP</th><th></th>
            </tr></thead>
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
