<x-layouts.app title="Mon compte">
    <div class="mb-5 flex items-center gap-3">
        <span class="flex size-14 items-center justify-center rounded-full bg-ink-800 text-lg font-bold text-white">{{ $user->initials() }}</span>
        <div>
            <h1 class="text-xl font-extrabold">{{ $user->name }}</h1>
            <p class="text-sm text-ink-500">{{ \App\Support\Phone::format($user->phone) }} · {{ $user->role->label() }}</p>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        @if ($user->isClient())
            {{-- Véhicules --}}
            <section class="card p-5">
                <h2 class="mb-3 font-bold">🚗 Mes véhicules</h2>
                <ul class="mb-4 divide-y divide-ink-50">
                    @forelse ($user->vehicles as $vehicle)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span><span class="chip mr-1 bg-ink-50 text-ink-600">{{ $vehicle->type->label() }}</span> {{ $vehicle->title() }}
                                @if ($vehicle->plate) <span class="text-ink-400">· {{ $vehicle->plate }}</span> @endif
                            </span>
                            <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}" onsubmit="return confirm('Supprimer ce véhicule ?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600">Supprimer</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-ink-400">Aucun véhicule enregistré.</li>
                    @endforelse
                </ul>
                <details @if ($errors->hasAny(['type', 'brand', 'model', 'year', 'plate'])) open @endif>
                    <summary class="btn btn-ghost btn-sm cursor-pointer">+ Ajouter un véhicule</summary>
                    <form method="POST" action="{{ route('vehicles.store') }}" class="mt-3 grid grid-cols-2 gap-3">
                        @csrf
                        <div>
                            <label class="field-label" for="type">Type</label>
                            <select id="type" name="type" class="input" required>
                                @foreach ($vehicleTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-field name="brand" label="Marque" placeholder="Toyota, Yamaha…" required />
                        <x-field name="model" label="Modèle" />
                        <x-field name="year" type="number" label="Année" />
                        <x-field name="plate" label="Immatriculation" class="col-span-2" />
                        <button class="btn btn-primary col-span-2">Enregistrer</button>
                    </form>
                </details>
            </section>

            {{-- Favoris --}}
            <section class="card p-5">
                <h2 class="mb-3 font-bold">❤️ Mes favoris</h2>
                <ul class="divide-y divide-ink-50">
                    @forelse ($favorites as $fav)
                        <li class="flex items-center justify-between gap-2 py-2 text-sm">
                            <a href="{{ route('providers.show', $fav) }}" class="font-medium hover:text-brand-600">{{ $fav->type->icon() }} {{ $fav->business_name }}
                                <span class="font-normal text-ink-400">{{ $fav->neighborhood?->name }}</span></a>
                            <a href="{{ $fav->callUrl() }}" class="btn btn-primary btn-sm">Appeler</a>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-ink-400">Ajoutez des prestataires en favoris pour les retrouver vite en cas de panne.</li>
                    @endforelse
                </ul>
            </section>

            @if (feature('marketplace_enabled') && feature('part_requests_enabled'))
                <section class="card p-5">
                    <h2 class="mb-1 font-bold">📣 Mes demandes de pièces</h2>
                    <p class="mb-3 text-sm text-ink-500">{{ $user->partRequests()->count() }} demande(s). Les boutiques vous répondent avec leur prix.</p>
                    <div class="flex gap-2">
                        <a href="{{ route('part-requests.index') }}" class="btn btn-ghost btn-sm">Voir mes demandes</a>
                        <a href="{{ route('part-requests.create') }}" class="btn btn-primary btn-sm">Nouvelle demande</a>
                    </div>
                </section>
            @endif

            {{-- Mes avis (historique) --}}
            <section class="card p-5">
                <h2 class="mb-3 font-bold">★ Mes avis</h2>
                <ul class="divide-y divide-ink-50">
                    @forelse ($reviews as $review)
                        <li class="py-2 text-sm">
                            <a href="{{ route('providers.show', $review->provider) }}" class="font-medium hover:text-brand-600">{{ $review->provider->business_name }}</a>
                            <span class="text-amber-500">{{ str_repeat('★', $review->rating) }}</span>
                            @if ($review->is_hidden) <span class="chip bg-ink-100 text-ink-500">masqué par la modération</span> @endif
                        </li>
                    @empty
                        <li class="py-2 text-sm text-ink-400">Vous n'avez pas encore donné d'avis.</li>
                    @endforelse
                </ul>
            </section>
        @endif

        {{-- Profil --}}
        <section class="card p-5">
            <h2 class="mb-3 font-bold">👤 Mes informations</h2>
            <form method="POST" action="{{ route('account.update') }}" class="space-y-3">
                @csrf @method('PUT')
                <x-field name="name" label="Nom complet" :value="$user->name" required />
                <x-field name="email" type="email" label="Email" :value="$user->email" :hint="$user->hasVerifiedEmail() ? 'Adresse confirmée ✓' : 'Adresse non confirmée'" required />
                <x-field name="phone" type="tel" label="Téléphone (facultatif)" :value="$user->phone" />
                <x-location-select :cities="$cities" :city="$user->city_id" :neighborhood="$user->neighborhood_id" />
                <button class="btn btn-primary">Enregistrer</button>
            </form>
        </section>

        <section class="card p-5">
            <h2 class="mb-3 font-bold">🔒 Mot de passe</h2>
            <form method="POST" action="{{ route('account.password') }}" class="space-y-3">
                @csrf @method('PUT')
                <x-field name="current_password" type="password" label="Mot de passe actuel" autocomplete="current-password" required />
                <x-field name="password" type="password" label="Nouveau mot de passe" autocomplete="new-password" required />
                <x-field name="password_confirmation" type="password" label="Confirmer" autocomplete="new-password" required />
                <button class="btn btn-dark">Changer le mot de passe</button>
            </form>
        </section>
    </div>
</x-layouts.app>
