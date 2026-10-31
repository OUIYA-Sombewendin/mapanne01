<x-layouts.pro title="Déclarer une boutique">
    <a href="{{ route('pro.shops.index') }}" class="text-sm text-ink-500">← Mes boutiques</a>
    <h1 class="mb-1 text-2xl font-extrabold">Déclarer une nouvelle boutique</h1>
    <p class="mb-5 text-sm text-ink-500">La boutique aura sa propre fiche, son stock et ses ventes. Elle sera visible des clients après vérification par l'équipe {{ setting('site_name') }}.</p>

    <form method="POST" action="{{ route('pro.shops.store') }}" class="card max-w-2xl space-y-4 p-5">
        @csrf
        <x-field name="business_name" label="Nom de la boutique" required placeholder="Ex. Pièces Auto Faso — Tampouy" />
        <div>
            <label class="field-label" for="type">Type</label>
            <select id="type" name="type" class="input" required>
                @foreach ($types as $type) <option value="{{ $type->slug }}" @selected(old('type') === $type->slug)>{{ $type->icon() }} {{ $type->label() }}</option> @endforeach
            </select>
            @error('type') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <x-field name="phone" type="tel" label="Téléphone de la boutique" required placeholder="70 00 00 00" />
        <x-location-select :cities="$cities" required />
        <x-field name="address" label="Adresse / repère" placeholder="Ex. face au marché, à côté de la pharmacie" />
        <button class="btn btn-primary">Déclarer la boutique</button>
        <p class="text-xs text-ink-400">Vous pourrez ensuite placer la boutique sur la carte, ajouter photos et horaires depuis sa fiche.</p>
    </form>
</x-layouts.pro>
