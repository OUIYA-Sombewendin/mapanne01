<x-layouts.pro title="Importer mon catalogue">
    <a href="{{ route('pro.products.index') }}" class="text-sm text-ink-500">← Catalogue</a>
    <h1 class="mt-2 mb-1 text-2xl font-extrabold">Importer mon catalogue</h1>
    <p class="mb-5 text-sm text-ink-500">Ajoutez ou mettez à jour des centaines de pièces en une fois. Une pièce qui porte déjà le même nom dans votre catalogue est mise à jour (prix, stock…).</p>

    @if (session('importErrors'))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Lignes ignorées :</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach (array_slice(session('importErrors'), 0, 30) as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="card space-y-4 p-5">
            <h2 class="font-bold">1. Préparez votre fichier</h2>
            <p class="text-sm text-ink-600">Téléchargez le modèle, remplissez-le dans Excel ou Google Sheets, puis enregistrez-le au format <strong>CSV</strong>.</p>
            <a href="{{ route('pro.products.template') }}" class="btn btn-ghost">⬇️ Télécharger le modèle</a>

            <h2 class="pt-2 font-bold">2. Envoyez-le</h2>
            <form method="POST" action="{{ route('pro.products.import') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" required class="text-sm">
                @error('file') <p class="field-error">{{ $message }}</p> @enderror
                <button class="btn btn-primary">Importer</button>
            </form>
            <p class="text-xs text-ink-400">Les photos s'ajoutent ensuite produit par produit. Maximum {{ \App\Support\CatalogImporter::MAX_ROWS }} lignes par fichier.</p>
        </section>

        <section class="card p-5">
            <h2 class="mb-3 font-bold">Colonnes du fichier</h2>
            <dl class="space-y-1 text-sm">
                @foreach ($columns as $column => $description)
                    <div class="flex gap-2"><dt class="w-28 shrink-0 font-mono text-xs text-brand-700">{{ $column }}</dt><dd class="text-ink-600">{{ $description }}</dd></div>
                @endforeach
            </dl>
            <h3 class="mt-4 mb-2 text-sm font-bold">Codes des catégories</h3>
            <div class="flex flex-wrap gap-1">
                @foreach ($categories as $c)
                    <span class="chip bg-ink-50 font-mono text-ink-700" title="{{ $c->name }}">{{ $c->slug }}</span>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.pro>
