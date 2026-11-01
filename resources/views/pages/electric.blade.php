<x-layouts.app title="Moto et voiture électriques">
    {{-- Héros --}}
    <section class="relative -mx-4 -mt-5 overflow-hidden bg-gradient-to-br from-emerald-900 via-ink-900 to-ink-900 px-4 pt-8 pb-10 text-white md:-mt-8 md:rounded-b-[2rem] md:px-10 md:pt-12 md:pb-12">
        <div class="pointer-events-none absolute -top-24 -right-20 size-80 rounded-full bg-emerald-400/20 blur-3xl"></div>
        <div class="relative max-w-3xl">
            <p class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold ring-1 ring-white/15">⚡ Le guide de l'électrique au Burkina Faso</p>
            <h1 class="text-3xl leading-tight font-extrabold md:text-5xl">Moto et voiture <span class="text-emerald-300">électriques</span></h1>
            <p class="mt-3 text-ink-100 md:text-lg">{{ setting('electric_intro') }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                @if (feature('assist_enabled'))
                    <a href="{{ route('assist.create', ['energy' => 'electrique', 'vehicle' => 'moto']) }}" class="btn bg-red-600 px-5 py-3 text-white hover:bg-red-500">🆘 Je suis en panne</a>
                @endif
                <a href="#carte" class="btn bg-white px-5 py-3 text-ink-900 hover:bg-emerald-50">Voir les spécialistes et la recharge</a>
            </div>
            <div class="mt-6 flex flex-wrap gap-6 text-sm">
                <p><span class="block text-2xl font-extrabold">{{ $specialists->count() }}</span>spécialiste(s) vérifié(s)</p>
                <p><span class="block text-2xl font-extrabold">{{ $points->count() }}</span>point(s) de recharge ou d'échange</p>
            </div>
        </div>
    </section>

    {{-- Pannes fréquentes --}}
    <section class="mt-8">
        <h2 class="mb-3 text-lg font-bold">Une panne ? Choisissez ce qui se passe</h2>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($problems as $key => $problem)
                <a href="{{ feature('assist_enabled') ? route('assist.create', ['energy' => 'electrique', 'problem' => $key]) : route('search', ['electric' => 1]) }}"
                   class="flex items-center gap-3 rounded-2xl border border-ink-100 bg-white p-3 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50">
                    <span class="text-2xl">{{ $problem['icon'] }}</span>
                    <span class="text-sm leading-tight font-semibold">{{ $problem['label'] }}</span>
                </a>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-ink-500">Les spécialistes électriques proches sont prévenus en premier.</p>
    </section>

    {{-- Carte et listes --}}
    <section id="carte" class="mt-10 scroll-mt-20">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">Spécialistes et points de recharge</h2>
                <p class="text-sm text-ink-500">⚡ réparateur spécialisé · 🔌 recharge · 🔄 échange de batterie</p>
            </div>
            <form class="flex gap-2">
                <select name="city" class="input-sm" onchange="this.form.submit()" aria-label="Ville">
                    <option value="">Toutes les villes</option>
                    @foreach ($cities as $c) <option value="{{ $c->id }}" @selected($city === $c->id)>{{ $c->name }}</option> @endforeach
                </select>
            </form>
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <div class="space-y-5">
                <div>
                    <h3 class="mb-2 font-semibold">⚡ Réparateurs spécialisés</h3>
                    <div class="space-y-3">
                        @forelse ($specialists as $provider)
                            <x-provider-card :provider="$provider" />
                        @empty
                            <p class="card p-4 text-sm text-ink-500">Aucun spécialiste vérifié {{ $city ? 'dans cette ville' : '' }} pour l'instant. <a href="{{ route('search', ['electric' => 1]) }}" class="text-accent-700 underline">Chercher quand même</a> ou appelez un mécanicien proche.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold">🔌 Recharge et échange de batterie</h3>
                    <ul class="space-y-2">
                        @forelse ($points as $point)
                            <li class="card flex items-start gap-3 p-4">
                                <span class="text-2xl">{{ $point->icon() }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $point->name }}</p>
                                    <p class="text-xs text-ink-500">{{ $point->kindLabel() }}{{ $point->vehicleLabels() ? ' · '.implode(', ', $point->vehicleLabels()) : '' }}</p>
                                    <p class="text-xs text-ink-500">{{ collect([$point->address, $point->city?->name])->filter()->implode(', ') }}{{ $point->hours ? ' · '.$point->hours : '' }}</p>
                                    @if ($point->notes) <p class="mt-1 text-xs text-ink-400">{{ $point->notes }}</p> @endif
                                </div>
                                <div class="flex shrink-0 flex-col gap-1">
                                    @if ($point->phone) <a href="tel:{{ $point->phone }}" class="btn btn-primary btn-sm">Appeler</a> @endif
                                    <a href="{{ $point->mapUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Itinéraire</a>
                                </div>
                            </li>
                        @empty
                            <li class="card p-4 text-sm text-ink-500">Aucun point référencé pour l'instant. Vous en connaissez un ? Écrivez-nous à {{ setting('support_email') }}.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div x-data="resultsMap(@js($markers), @js($center), false)" class="card overflow-hidden lg:sticky lg:top-20 lg:self-start">
                <div x-ref="map" class="h-[55dvh] w-full lg:h-[calc(100dvh-8rem)]"></div>
            </div>
        </div>
    </section>

    {{-- Conseils --}}
    <section class="mt-12">
        <h2 class="mb-4 text-2xl font-extrabold">Les bons gestes</h2>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['🔑', 'Ma moto électrique ne démarre plus', ['Regardez le niveau de charge affiché.', 'Relevez bien la béquille : beaucoup de modèles coupent le moteur quand elle est baissée.', 'Relâchez les freins : sur certains modèles, un frein serré empêche de démarrer.', 'Vérifiez que le connecteur de la batterie est bien enfoncé et que le fusible n\'a pas sauté.']],
                ['🔋', 'Bien charger sa batterie', ['Utilisez le chargeur d\'origine.', 'Chargez à l\'ombre, pas juste après avoir roulé en pleine chaleur.', 'Évitez de descendre à zéro : rechargez vers 20 %.', 'Débranchez une fois la charge terminée.']],
                ['☀️', 'Chaleur, poussière et pluie', ['Garez à l\'ombre : la chaleur use la batterie.', 'Essuyez la poussière des connecteurs.', 'Pas de lavage au jet sur le moteur et la batterie.', 'Évitez de traverser les rues inondées.']],
                ['⚠️', 'Danger : arrêtez tout', ['Batterie gonflée, odeur de brûlé, fumée ou batterie très chaude : ne la chargez plus.', 'Éloignez le véhicule des personnes et de la maison.', 'N\'ouvrez jamais une batterie vous-même : appelez un spécialiste.']],
                ['🛒', 'Avant d\'acheter', ['Demandez s\'il existe un réparateur et des pièces dans votre ville.', 'Renseignez-vous sur la garantie de la batterie (durée, conditions).', 'Demandez l\'autonomie réelle, avec un passager et par forte chaleur.']],
                ['🛠️', 'Entretien régulier', ['Contrôlez la batterie et les câbles tous les 3 mois.', 'Pneus et freins s\'usent comme sur une moto à essence.', 'Notez vos entretiens dans le carnet : on vous rappelle les échéances.']],
            ] as [$icon, $title, $tips])
                <div class="card p-5">
                    <h3 class="flex items-center gap-2 font-bold"><span class="text-2xl">{{ $icon }}</span>{{ $title }}</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-ink-600">
                        @foreach ($tips as $tip) <li>{{ $tip }}</li> @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Appels à l'action --}}
    <section class="mt-10 grid gap-4 md:grid-cols-2">
        @if (feature('logbook_enabled'))
            <div class="rounded-3xl bg-emerald-50 p-6 ring-1 ring-emerald-200">
                <h2 class="text-xl font-extrabold text-emerald-900">🛠️ Carnet d'entretien gratuit</h2>
                <p class="mt-1 text-sm text-emerald-900/80">Enregistrez votre véhicule électrique : on vous rappelle le contrôle de la batterie, l'assurance et le reste.</p>
                <a href="{{ auth()->check() && auth()->user()->isClient() ? route('logbook.index') : route('register') }}" class="btn btn-primary mt-4">Créer mon carnet</a>
            </div>
        @endif
        <div class="rounded-3xl bg-ink-900 p-6 text-white">
            <h2 class="text-xl font-extrabold">Vous réparez des véhicules électriques ?</h2>
            <p class="mt-1 text-sm text-ink-200">Obtenez le badge « ⚡ Spécialiste électrique » : votre fiche apparaît sur cette page et vous êtes prévenu en premier pour les pannes électriques.</p>
            <a href="{{ auth()->check() && auth()->user()->isProvider() ? route('pro.verification') : route('register', ['type' => 'pro']) }}" class="btn mt-4 bg-white text-ink-900 hover:bg-emerald-50">{{ auth()->check() && auth()->user()->isProvider() ? 'Demander le badge' : 'Inscrire mon atelier' }}</a>
        </div>
    </section>
</x-layouts.app>
