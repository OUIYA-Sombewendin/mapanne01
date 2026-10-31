<x-layouts.admin title="Sauvegardes">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">Sauvegardes</h1>
            <p class="text-sm text-ink-500">Base de données complète et photos dans une archive .zip, stockée hors du dossier public. Téléchargez-en une copie régulièrement.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.settings.index', 'backups') }}" class="btn btn-ghost"><x-icon name="settings" /> Options</a>
            <form method="POST" action="{{ route('admin.backups.store') }}" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button class="btn btn-primary" :disabled="busy"><x-icon name="database" /> <span x-text="busy ? 'Sauvegarde en cours…' : 'Sauvegarder maintenant'">Sauvegarder maintenant</span></button>
            </form>
        </div>
    </div>

    <div class="mb-5 grid gap-3 md:grid-cols-3">
        <div class="card p-4"><p class="text-xs text-ink-500">Sauvegarde automatique</p><p class="font-bold">{{ feature('backup_auto_enabled') ? 'Chaque nuit à 2 h' : 'Désactivée' }}</p></div>
        <div class="card p-4"><p class="text-xs text-ink-500">Conservées</p><p class="font-bold">{{ count($backups) }} / {{ (int) setting('backup_keep') }} max.</p></div>
        <div class="card p-4"><p class="text-xs text-ink-500">Outil PostgreSQL</p><p class="truncate font-mono text-xs" title="{{ $tool }}">{{ $tool }}</p></div>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Fichier</th><x-sort-th column="date" label="Date" /><x-sort-th column="size" label="Taille" align="right" /><th></th></tr></thead>
            <tbody>
                @forelse ($backups as $b)
                    <tr x-data="{ restore: false }">
                        <td class="font-mono text-xs">{{ $b['name'] }}</td>
                        <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::createFromTimestamp($b['date'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                        <td class="text-right whitespace-nowrap">{{ number_format($b['size'] / 1048576, 1, ',', ' ') }} Mo</td>
                        <td>
                            <div class="flex flex-wrap justify-end gap-1">
                                <a href="{{ route('admin.backups.download', $b['name']) }}" class="btn btn-ghost btn-sm">Télécharger</a>
                                @if (auth()->user()->isSuperAdmin())
                                    <button @click="restore = !restore" class="btn btn-ghost btn-sm">Restaurer</button>
                                @endif
                                <form method="POST" action="{{ route('admin.backups.destroy', $b['name']) }}" onsubmit="return confirm('Supprimer cette sauvegarde ?')">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-red-600">Supprimer</button>
                                </form>
                            </div>
                            <form x-cloak x-show="restore" method="POST" action="{{ route('admin.backups.restore', $b['name']) }}" class="mt-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                                @csrf
                                <p class="mb-2">⚠️ Toutes les données actuelles seront remplacées par celles du {{ \Illuminate\Support\Carbon::createFromTimestamp($b['date'])->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}. Une sauvegarde de sécurité de l'état actuel sera faite avant. Tapez <strong>RESTAURER</strong> pour confirmer.</p>
                                <div class="flex gap-2">
                                    <input name="confirm" class="input py-1.5" autocomplete="off" placeholder="RESTAURER">
                                    <button class="btn btn-danger btn-sm">Restaurer</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-ink-400">Aucune sauvegarde pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-ink-400">Conseil : gardez aussi une copie hors du serveur (ordinateur, clé USB ou stockage en ligne). La restauration est réservée au super-administrateur.</p>
</x-layouts.admin>
