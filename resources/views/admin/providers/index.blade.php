<x-layouts.admin title="Prestataires">
    <h1 class="mb-4 text-2xl font-extrabold">Prestataires</h1>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($statuses as $s)
            <a href="{{ route('admin.providers.index', ['status' => $s->value]) }}" @class([
                'chip px-3 py-1.5 text-sm',
                'bg-ink-800 text-white' => $filter === $s->value,
                'bg-white text-ink-600 ring-1 ring-ink-200' => $filter !== $s->value,
            ])>{{ $s->label() }} <span class="opacity-70">{{ $counts[$s->value] ?? 0 }}</span></a>
        @endforeach
        <a href="{{ route('admin.providers.index', ['status' => 'review']) }}" @class(['chip px-3 py-1.5 text-sm', 'bg-ink-800 text-white' => $filter === 'review', 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' => $filter !== 'review'])>
            Changements à vérifier <span class="opacity-70">{{ $reviewCount }}</span></a>
        <a href="{{ route('admin.providers.index', ['status' => 'featured']) }}" @class(['chip px-3 py-1.5 text-sm', 'bg-ink-800 text-white' => $filter === 'featured', 'bg-white text-ink-600 ring-1 ring-ink-200' => $filter !== 'featured'])>⭐ À la une</a>
        <a href="{{ route('admin.providers.index', ['status' => 'all']) }}" @class(['chip px-3 py-1.5 text-sm', 'bg-ink-800 text-white' => $filter === 'all', 'bg-white text-ink-600 ring-1 ring-ink-200' => $filter !== 'all'])>Tous</a>
    </div>

    <form class="mb-4 flex flex-wrap gap-2">
        <input type="hidden" name="status" value="{{ $filter }}">
        <input name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Nom, email ou téléphone">
        <select name="type" class="input max-w-56">
            <option value="">Tous types</option>
            @foreach ($types as $t)
                <option value="{{ $t->slug }}" @selected(request('type') === $t->slug)>{{ $t->icon() }} {{ $t->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark">Filtrer</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Prestataire</th><th>Type</th><th>Ville</th><th>Contact</th><th>Statut</th><th>Inscrit</th><th></th></tr></thead>
            <tbody>
                @forelse ($providers as $p)
                    <tr>
                        <td class="font-semibold">{{ $p->business_name }}
                            @if ($p->is_featured) <span title="À la une">⭐</span> @endif
                            @if ($p->needs_review) <span class="chip bg-amber-100 text-amber-800">changements à vérifier</span> @endif
                            @if (! $p->user->is_active) <span class="chip bg-red-100 text-red-700">compte suspendu</span> @endif
                        </td>
                        <td>{{ $p->type->label() }}</td>
                        <td>{{ $p->city?->name }}</td>
                        <td class="whitespace-nowrap">{{ \App\Support\Phone::format($p->phone) }}<br><span class="text-xs text-ink-400">{{ $p->user->email }}</span></td>
                        <td><span class="chip {{ $p->status->color() }}">{{ $p->status->label() }}</span></td>
                        <td class="whitespace-nowrap text-ink-500">{{ $p->created_at->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap"><a href="{{ route('admin.providers.show', $p) }}" class="btn btn-ghost btn-sm">Ouvrir</a> <x-lock-button :model="$p" compact /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-ink-400">Aucun prestataire.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $providers->links() }}</div>
</x-layouts.admin>
