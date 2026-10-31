<x-layouts.admin title="Prestataires">
    <h1 class="mb-4 text-2xl font-extrabold">Prestataires</h1>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($statuses as $s)
            <a href="{{ route('admin.providers.index', ['status' => $s->value] + request()->except(['status', 'page'])) }}" @class([
                'chip px-3 py-1.5 text-sm',
                'bg-ink-800 text-white' => $filter === $s->value,
                'bg-white text-ink-600 ring-1 ring-ink-200' => $filter !== $s->value,
            ])>{{ $s->label() }} <span class="opacity-70">{{ $counts[$s->value] ?? 0 }}</span></a>
        @endforeach
        <a href="{{ route('admin.providers.index', ['status' => 'review'] + request()->except(['status', 'page'])) }}" @class(['chip px-3 py-1.5 text-sm', 'bg-ink-800 text-white' => $filter === 'review', 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' => $filter !== 'review'])>
            Changements à vérifier <span class="opacity-70">{{ $reviewCount }}</span></a>
        <a href="{{ route('admin.providers.index', ['status' => 'featured'] + request()->except(['status', 'page'])) }}" @class(['chip px-3 py-1.5 text-sm', 'bg-ink-800 text-white' => $filter === 'featured', 'bg-white text-ink-600 ring-1 ring-ink-200' => $filter !== 'featured'])>⭐ À la une</a>
        <a href="{{ route('admin.providers.index', ['status' => 'all'] + request()->except(['status', 'page'])) }}" @class(['chip px-3 py-1.5 text-sm', 'bg-ink-800 text-white' => $filter === 'all', 'bg-white text-ink-600 ring-1 ring-ink-200' => $filter !== 'all'])>Tous</a>
    </div>

    <x-filter-bar :keys="['q', 'type', 'city', 'night', 'formal', 'photos', 'blocked', 'from']" :keep="['status']">
        <input type="hidden" name="status" value="{{ $filter }}">
        <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-56" placeholder="Nom, email ou téléphone">
        <select name="type" class="input-sm">
            <option value="">Tous les métiers</option>
            @foreach ($types as $t)
                <option value="{{ $t->slug }}" @selected(request('type') === $t->slug)>{{ $t->icon() }} {{ $t->label() }}</option>
            @endforeach
        </select>
        <select name="city" class="input-sm">
            <option value="">Toutes les villes</option>
            @foreach ($cities as $c) <option value="{{ $c->id }}" @selected(request('city') == $c->id)>{{ $c->name }}</option> @endforeach
        </select>
        @include('admin._flag', ['name' => 'night', 'label' => 'Nuit'])
        @include('admin._flag', ['name' => 'formal', 'label' => 'Entreprise'])
        @include('admin._flag', ['name' => 'photos', 'label' => 'Photos'])
        @include('admin._flag', ['name' => 'blocked', 'label' => 'Compte bloqué'])
        <label class="flex items-center gap-1 text-sm text-ink-500">Inscrit depuis <input type="date" name="from" value="{{ request('from') }}" class="input-sm"></label>
    </x-filter-bar>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr>
                <x-sort-th column="name" label="Prestataire" />
                <x-sort-th column="type" label="Type" />
                <x-sort-th column="city" label="Ville" />
                <th>Contact</th>
                <x-sort-th column="rating" label="Note" align="center" />
                <x-sort-th column="status" label="Statut" />
                <x-sort-th column="created" label="Inscrit" />
                <th></th>
            </tr></thead>
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
                        <td class="text-center whitespace-nowrap">{{ $p->reviews_count ? '★ '.number_format($p->rating_avg, 1, ',', '') : '—' }}</td>
                        <td><span class="chip {{ $p->status->color() }}">{{ $p->status->label() }}</span></td>
                        <td class="whitespace-nowrap text-ink-500">{{ $p->created_at->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap"><a href="{{ route('admin.providers.show', $p) }}" class="btn btn-ghost btn-sm">Ouvrir</a> <x-lock-button :model="$p" compact /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-ink-400">Aucun prestataire.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $providers->links() }}</div>
</x-layouts.admin>
