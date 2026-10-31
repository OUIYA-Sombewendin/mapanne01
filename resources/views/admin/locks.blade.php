<x-layouts.admin title="Verrous">
    <h1 class="mb-1 text-2xl font-extrabold">Éléments verrouillés</h1>
    <p class="mb-5 text-sm text-ink-500">Un élément verrouillé ne peut plus être modifié, validé, suspendu ni supprimé depuis l'administration, même par erreur. Le verrou ne gêne pas l'utilisateur concerné. Posez un verrou avec le bouton 🔓 présent dans chaque liste.</p>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Élément</th><th>Type</th><th>Motif</th><th>Verrouillé par</th><th>Depuis</th><th></th></tr></thead>
            <tbody>
                @forelse ($locks as $lock)
                    <tr>
                        <td class="font-semibold">{{ $lock->lockableName() }}</td>
                        <td>{{ $lock->typeLabel() }}</td>
                        <td class="text-ink-500">{{ $lock->reason ?: '—' }}</td>
                        <td>{{ $lock->locker?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-ink-500">{{ $lock->created_at?->format('d/m/Y H:i') }}</td>
                        <td>@if ($lock->lockable) <x-lock-button :model="$lock->lockable" /> @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-ink-400">Aucun élément verrouillé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $locks->links() }}</div>
</x-layouts.admin>
