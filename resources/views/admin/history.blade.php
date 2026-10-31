<x-layouts.admin title="Historique">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">Historique des actions</h1>
            <p class="text-sm text-ink-500">Qui a fait quoi, et quand : validations, modérations, réglages, abonnements, sauvegardes, connexions à l'administration.</p>
        </div>
        @can('data.export')
            <a href="{{ route('admin.history.export', request()->query()) }}" class="btn btn-ghost"><x-icon name="transfer" /> Exporter (Excel)</a>
        @endcan
    </div>

    <form class="card mb-4 grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input name="q" value="{{ request('q') }}" class="input lg:col-span-2" placeholder="Rechercher dans les détails…">
        <select name="domain" class="input">
            <option value="">Tous les domaines</option>
            @foreach ($domains as $key => $label) <option value="{{ $key }}" @selected(request('domain') === $key)>{{ $label }}</option> @endforeach
        </select>
        <select name="user" class="input">
            <option value="">Toute l'équipe</option>
            @foreach ($admins as $a) <option value="{{ $a->id }}" @selected(request('user') == $a->id)>{{ $a->name }}</option> @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="input" aria-label="Du">
        <div class="flex gap-2"><input type="date" name="to" value="{{ request('to') }}" class="input" aria-label="Au"><button class="btn btn-dark">OK</button></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Par</th><th>Action</th><th>Objet</th><th>Détails</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-ink-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap">{{ $log->user?->name ?? 'Système' }}</td>
                        <td class="font-medium">{{ $log->label() }}</td>
                        <td class="whitespace-nowrap text-ink-500">{{ $log->subjectLabel() }}</td>
                        <td class="max-w-md text-xs text-ink-500">
                            @foreach ((array) $log->details as $k => $v)
                                <span class="mr-2"><span class="text-ink-400">{{ $k }} :</span> {{ \Illuminate\Support\Str::limit(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v, 120) }}</span>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-ink-400">Aucune action.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.admin>
