<x-layouts.admin title="Contenus">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">Pages de contenu</h1>
            <p class="text-sm text-ink-500">CGU, confidentialité, FAQ, à propos… rédigées en Markdown, affichées dans le pied de page.</p>
        </div>
        <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">+ Nouvelle page</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Titre</th><th>Adresse</th><th>Statut</th><th>Pied de page</th><th>Modifiée</th><th></th></tr></thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr>
                        <td class="font-medium">{{ $page->title }}</td>
                        <td><a href="{{ route('pages.show', $page) }}" target="_blank" class="text-brand-600">/pages/{{ $page->slug }}</a></td>
                        <td>{{ $page->is_published ? 'Publiée' : 'Brouillon' }}</td>
                        <td>{{ $page->in_footer ? 'Oui' : 'Non' }}</td>
                        <td class="text-ink-500">{{ $page->updated_at?->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap"><a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-ghost btn-sm">Modifier</a> <x-lock-button :model="$page" compact /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
