<x-layouts.admin :title="$page->exists ? $page->title : 'Nouvelle page'">
    <div class="mb-4"><x-back :href="route('admin.pages.index')" label="Contenus" /></div>
    <h1 class="mt-2 mb-4 text-2xl font-extrabold">{{ $page->exists ? 'Modifier « '.$page->title.' »' : 'Nouvelle page' }}</h1>

    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="card space-y-4 p-5">
        @csrf
        @if ($page->exists) @method('PUT') @endif
        <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_6rem]">
            <x-field name="title" label="Titre" :value="$page->title" required />
            <x-field name="slug" label="Adresse (/pages/…)" :value="$page->slug" hint="Laissez vide pour la générer." />
            <x-field name="position" type="number" label="Ordre" :value="$page->position ?? 0" />
        </div>
        <div>
            <label for="body" class="field-label">Contenu</label>
            <textarea id="body" name="body" rows="18" class="input font-mono text-sm">{{ old('body', $page->body) }}</textarea>
            <p class="mt-1 text-xs text-ink-400">Markdown : <code>## Titre</code>, <code>**gras**</code>, <code>- liste</code>, <code>[lien](https://…)</code>. Le HTML est désactivé pour la sécurité.</p>
        </div>
        <div class="flex flex-wrap gap-5 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="is_published" value="1" class="checkbox" @checked(old('is_published', $page->is_published))> Publiée</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="in_footer" value="1" class="checkbox" @checked(old('in_footer', $page->in_footer))> Lien dans le pied de page</label>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-primary">Enregistrer</button>
            @if ($page->exists)
                <a href="{{ route('pages.show', $page) }}" target="_blank" class="btn btn-ghost">Voir</a>
            @endif
        </div>
    </form>

    @if ($page->exists && ! in_array($page->slug, ['conditions-utilisation', 'confidentialite'], true))
        <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="mt-3" onsubmit="return confirm('Supprimer cette page ?')">
            @csrf @method('DELETE')
            <button class="text-sm text-red-600">Supprimer la page</button>
        </form>
    @endif
</x-layouts.admin>
