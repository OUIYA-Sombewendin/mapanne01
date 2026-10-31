<x-layouts.admin title="Signalements · Admin">
    <h1 class="mb-4 text-2xl font-extrabold">Signalements</h1>

    <div class="mb-4 flex gap-2">
        @foreach ($statuses as $s)
            <a href="{{ route('admin.reports.index', ['status' => $s->value] + request()->only(['q', 'reason', 'target', 'from', 'sort', 'dir'])) }}" @class([
                'chip px-3 py-1.5 text-sm',
                'bg-ink-800 text-white' => $status === $s,
                'bg-white text-ink-600 ring-1 ring-ink-200' => $status !== $s,
            ])>{{ $s->label() }}</a>
        @endforeach
    </div>

    <x-filter-bar :keys="['q', 'reason', 'target', 'from']" :keep="['status']">
        <input type="hidden" name="status" value="{{ $status?->value }}">
        <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-56" placeholder="Prestataire ou détails">
        <select name="reason" class="input-sm">
            <option value="">Tous les motifs</option>
            @foreach ($reasons as $k => $l) <option value="{{ $k }}" @selected(request('reason') === $k)>{{ $l }}</option> @endforeach
        </select>
        <select name="target" class="input-sm">
            <option value="">Fiches et avis</option>
            <option value="provider" @selected(request('target') === 'provider')>Fiches</option>
            <option value="review" @selected(request('target') === 'review')>Avis</option>
        </select>
        <label class="flex items-center gap-1 text-sm text-ink-500">Depuis <input type="date" name="from" value="{{ request('from') }}" class="input-sm"></label>
        <select name="sort" class="input-sm ml-auto" aria-label="Trier">
            <option value="date" @selected(request('sort', 'date') === 'date')>Trier : date</option>
            <option value="reason" @selected(request('sort') === 'reason')>Trier : motif</option>
        </select>
        <select name="dir" class="input-sm" aria-label="Ordre">
            <option value="desc" @selected(request('dir', 'desc') === 'desc')>Décroissant</option>
            <option value="asc" @selected(request('dir') === 'asc')>Croissant</option>
        </select>
    </x-filter-bar>

    <div class="space-y-3">
        @forelse ($reports as $r)
            <article class="card flex flex-wrap items-start justify-between gap-3 p-4">
                <div class="min-w-0 flex-1 text-sm">
                    <p class="font-semibold">{{ $r->reasonLabel() }}
                        @if ($r->provider) — <a href="{{ route('admin.providers.show', $r->provider) }}" class="text-brand-600">{{ $r->provider->business_name }}</a> @endif
                    </p>
                    @if ($r->details) <p class="mt-1 text-ink-700">{{ $r->details }}</p> @endif
                    @if ($r->review) <p class="mt-1 rounded bg-ink-50 p-2 text-xs">Avis visé : {{ str_repeat('★', $r->review->rating) }} « {{ $r->review->comment }} »</p> @endif
                    <p class="mt-1 text-xs text-ink-400">Par {{ $r->reporter?->name ?? 'compte supprimé' }} · {{ $r->created_at->format('d/m/Y H:i') }}
                        @if ($r->handler) · traité par {{ $r->handler->name }} @endif
                    </p>
                </div>
                @if ($r->status === \App\Enums\ReportStatus::Open)
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.reports.handle', $r) }}">@csrf<input type="hidden" name="status" value="resolved"><button class="btn btn-primary btn-sm">Traité</button></form>
                        <form method="POST" action="{{ route('admin.reports.handle', $r) }}">@csrf<input type="hidden" name="status" value="dismissed"><button class="btn btn-ghost btn-sm">Sans suite</button></form>
                    </div>
                @else
                    <span class="chip bg-ink-100 text-ink-600">{{ $r->status->label() }}</span>
                @endif
            </article>
        @empty
            <p class="card p-6 text-center text-sm text-ink-400">Aucun signalement.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $reports->links() }}</div>
</x-layouts.admin>
