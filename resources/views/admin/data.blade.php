<x-layouts.admin title="Import / export">
    <h1 class="mb-1 text-2xl font-extrabold">Import et export</h1>
    <p class="mb-5 text-sm text-ink-500">Les fichiers CSV s'ouvrent dans Excel (séparateur « ; »). Pour importer : dans Excel, « Enregistrer sous › CSV ». Chaque import et export est noté dans l'historique.</p>

    <div class="grid gap-5 xl:grid-cols-2">
        @if ($exports)
            <section class="card p-5">
                <h2 class="mb-3 flex items-center gap-2 font-bold"><x-icon name="transfer" /> Exporter</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($exports as $key => [$label])
                        <a href="{{ route('admin.data.export', $key) }}" class="flex items-center gap-2 rounded-xl border border-ink-100 px-4 py-3 text-sm font-medium hover:border-brand-300 hover:bg-brand-50">⬇ {{ $label }}</a>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-ink-400">Les mots de passe ne sont jamais exportés. L'export des réglages sert à copier la configuration vers un autre serveur.</p>
            </section>
        @endif

        @if ($imports)
            <section class="card space-y-4 p-5">
                <h2 class="flex items-center gap-2 font-bold"><x-icon name="transfer" class="rotate-180" /> Importer</h2>
                @foreach ($imports as $key => [$label, $permission, $columns])
                    <form method="POST" action="{{ route('admin.data.import', $key) }}" enctype="multipart/form-data" class="rounded-xl border border-ink-100 p-4">
                        @csrf
                        <p class="font-semibold">{{ $label }}</p>
                        <p class="mb-2 text-xs text-ink-500">Colonnes : <code>{{ $columns }}</code></p>
                        @if ($key === 'catalogue')
                            <select name="provider_id" class="input mb-2" required aria-label="Boutique">
                                <option value="">Boutique…</option>
                                @foreach ($shops as $shop) <option value="{{ $shop->id }}">{{ $shop->business_name }}</option> @endforeach
                            </select>
                        @endif
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="file" name="file" accept="{{ $key === 'reglages' ? '.json,application/json' : '.csv,text/csv' }}" class="text-sm" required>
                            <button class="btn btn-dark btn-sm">Importer</button>
                        </div>
                    </form>
                @endforeach
                <p class="text-xs text-ink-400">Les éléments existants ne sont pas dupliqués : seuls les nouveaux sont ajoutés (le catalogue met à jour les pièces de même nom).</p>
            </section>
        @endif
    </div>
</x-layouts.admin>
