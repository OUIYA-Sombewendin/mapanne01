<x-layouts.admin title="Justificatifs">
    <h1 class="mb-1 text-2xl font-extrabold">Badges de confiance</h1>
    <p class="mb-5 text-sm text-ink-500">Justificatifs envoyés par les prestataires. Comparez le nom et la photo avec la fiche, puis validez (le badge apparaît aussitôt) ou refusez avec un motif.</p>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['pending' => 'À vérifier', 'approved' => 'Validés', 'rejected' => 'Refusés', 'all' => 'Tous'] as $key => $label)
            <a href="{{ route('admin.verifications.index', ['status' => $key] + request()->except(['status', 'page'])) }}" @class([
                'chip px-3 py-1.5 text-sm',
                'on-color bg-accent-700 text-white' => $status === $key,
                'bg-white text-ink-600 ring-1 ring-ink-200' => $status !== $key,
            ])>{{ $label }} @if ($key !== 'all') <span class="opacity-70">{{ $counts[$key] ?? 0 }}</span> @endif</a>
        @endforeach
    </div>

    <x-filter-bar :keys="['q', 'kind', 'type']" :keep="['status']">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-56" placeholder="Prestataire…">
        <select name="kind" class="input-sm">
            <option value="">Tous les documents</option>
            @foreach ($kinds as $key => [$label]) <option value="{{ $key }}" @selected(request('kind') === $key)>{{ $label }}</option> @endforeach
        </select>
        <select name="type" class="input-sm">
            <option value="">Tous les métiers</option>
            @foreach ($types as $t) <option value="{{ $t->slug }}" @selected(request('type') === $t->slug)>{{ $t->icon() }} {{ $t->label() }}</option> @endforeach
        </select>
    </x-filter-bar>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($documents as $doc)
            <article class="card flex gap-4 p-4">
                <a href="{{ route('admin.verifications.file', $doc) }}" target="_blank" rel="noopener" class="shrink-0" title="Ouvrir en grand">
                    <img src="{{ route('admin.verifications.file', $doc) }}" alt="{{ $doc->kindLabel() }}" class="h-36 w-28 rounded-xl object-cover shadow ring-1 ring-ink-100" loading="lazy">
                </a>
                <div class="min-w-0 flex-1">
                    <p class="font-extrabold">
                        @can('providers.view')<a href="{{ route('admin.providers.show', $doc->provider) }}" class="hover:text-accent-700">{{ $doc->provider->type->icon() }} {{ $doc->provider->business_name }}</a>@endcan
                    </p>
                    <p class="text-sm text-ink-500">Responsable : {{ $doc->provider->manager_name ?: '—' }} · {{ \App\Support\Phone::format($doc->provider->phone) }}</p>
                    <p class="mt-2 text-sm font-semibold">{{ $doc->kindLabel() }}</p>
                    @if ($doc->number) <p class="font-mono text-sm">N° {{ $doc->number }}</p> @endif
                    <p class="mt-1 text-xs text-ink-400">Envoyé le {{ $doc->created_at->format('d/m/Y H:i') }}
                        @if ($doc->reviewer) · traité par {{ $doc->reviewer->name }} le {{ $doc->reviewed_at->format('d/m/Y') }} @endif</p>
                    <span class="chip mt-1 {{ $doc->statusClass() }}">{{ $doc->statusLabel() }}</span>
                    @if ($doc->rejected_reason) <p class="mt-1 text-sm text-red-700">Motif : {{ $doc->rejected_reason }}</p> @endif

                    @if ($doc->status === 'pending')
                        @can('providers.manage')
                            <div class="mt-3 space-y-2" x-data="{ refusing: false }">
                                <form method="POST" action="{{ route('admin.verifications.review', $doc) }}" class="flex flex-wrap gap-2">
                                    @csrf
                                    <button name="decision" value="approve" class="btn btn-primary btn-sm">✓ Valider</button>
                                    <button type="button" class="btn btn-ghost btn-sm text-red-700" @click="refusing = !refusing">Refuser…</button>
                                </form>
                                <form x-cloak x-show="refusing" method="POST" action="{{ route('admin.verifications.review', $doc) }}" class="flex gap-2">
                                    @csrf <input type="hidden" name="decision" value="reject">
                                    <input name="reason" class="input-sm flex-1" placeholder="Motif (ex. photo illisible, nom différent)" required maxlength="255">
                                    <button class="btn btn-ghost btn-sm text-red-700">Refuser</button>
                                </form>
                            </div>
                        @endcan
                    @endif
                </div>
            </article>
        @empty
            <p class="card p-6 text-center text-ink-400 lg:col-span-2">Aucun document dans cette liste.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $documents->links() }}</div>
</x-layouts.admin>
