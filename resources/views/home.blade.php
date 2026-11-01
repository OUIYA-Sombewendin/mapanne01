<x-layouts.app>
    {{-- Héros : une seule question, « où êtes-vous et de quoi avez-vous besoin ? » --}}
    <section class="relative -mx-4 -mt-5 overflow-hidden bg-ink-900 px-4 pt-8 pb-10 text-white md:-mt-8 md:rounded-b-[2rem] md:px-10 md:pt-14 md:pb-14">
        <div class="pointer-events-none absolute -top-32 -right-24 size-96 rounded-full bg-brand-500/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 -left-20 size-80 rounded-full bg-sky-500/10 blur-3xl"></div>

        <div class="relative grid items-center gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
            <div>
                <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium ring-1 ring-white/15">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60"></span><span class="relative size-2 rounded-full bg-emerald-400"></span></span>
                    {{ $stats['providers'] }} professionnels vérifiés · {{ $stats['night'] }} ouverts la nuit
                </p>
                <h1 class="text-4xl leading-[1.05] font-extrabold tracking-tight md:text-6xl">{{ setting('home_title') }}<br><span class="text-brand-400">{{ setting('home_highlight') }}</span></h1>
                <p class="mt-4 max-w-xl text-ink-200 md:text-lg">{{ setting('home_subtitle') }}</p>

                @if (feature('assist_enabled'))
                    {{-- Panne : le véhicule d'abord, puis le problème → demande de dépannage pré-remplie --}}
                    <div class="mt-6 max-w-xl overflow-hidden rounded-2xl bg-red-600 shadow-xl shadow-red-900/30"
                         x-data="{ vehicle: 'moto', energy: 'thermique', pick(v, e) { this.vehicle = v; this.energy = e } }">
                        <a :href="@js(route('assist.create')) + '?vehicle=' + vehicle + '&energy=' + energy" class="flex items-center gap-4 px-5 pt-4 pb-3 transition hover:bg-red-500">
                            <span class="text-3xl">🆘</span>
                            <span class="flex-1"><span class="block text-lg font-extrabold">Je suis en panne</span><span class="block text-sm text-white/85">Les pros les plus proches sont prévenus tout de suite.</span></span>
                            <span class="text-2xl">→</span>
                        </a>
                        <div class="bg-red-700/60 px-4 pt-3 pb-4">
                            <div class="grid grid-cols-3 gap-2 text-[13px] font-semibold sm:text-sm">
                                @foreach ([['moto', 'thermique', '🛵', 'Moto'], ['voiture', 'thermique', '🚗', 'Voiture'], ['moto', 'electrique', '⚡', 'Électrique']] as [$v, $e, $icon, $label])
                                    <button type="button" @click="pick(@js($v), @js($e))"
                                            :class="vehicle === @js($v) && energy === @js($e) ? 'bg-white text-red-700' : 'bg-white/10 text-white hover:bg-white/20'"
                                            class="rounded-xl px-2 py-2 whitespace-nowrap transition">{{ $icon }} {{ $label }}</button>
                                @endforeach
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($assistProblems as $key => $problem)
                                    <a :href="@js(route('assist.create')) + '?vehicle=' + vehicle + '&energy=' + energy + '&problem={{ $key }}'"
                                       @if (! empty($problem['electric'])) x-show="energy === 'electrique'" @endif
                                       @if (! empty($problem['fuel'])) x-show="energy !== 'electrique'" @endif
                                       @if ($key === 'transport_moto') x-show="vehicle === 'moto'" @endif
                                       class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium ring-1 ring-white/20 hover:bg-white hover:text-red-700">{{ $problem['icon'] }} {{ $problem['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <form action="{{ route('search') }}" method="GET" class="mt-6 max-w-xl rounded-2xl bg-white p-3 text-ink-900 shadow-2xl shadow-black/30" x-data="geoSearch">
                    <input type="hidden" name="lat"><input type="hidden" name="lng">
                    <button type="button" @click="locate()" :disabled="loading" class="btn btn-primary w-full py-3.5 text-base">
                        <svg x-show="!loading" class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
                        <svg x-cloak x-show="loading" class="size-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-dasharray="40 20"/></svg>
                        <span x-text="loading ? 'Localisation…' : 'Trouver de l\'aide autour de moi'">Trouver de l'aide autour de moi</span>
                    </button>
                    <p x-cloak x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>

                    <div class="my-3 flex items-center gap-3 text-xs text-ink-400"><span class="h-px flex-1 bg-ink-100"></span>ou cherchez<span class="h-px flex-1 bg-ink-100"></span></div>

                    <div class="grid gap-2 sm:grid-cols-[1fr_1.3fr_auto]">
                        <select name="city" class="input" aria-label="Ville">
                            <option value="">Toutes les villes</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                            @endforeach
                        </select>
                        <input name="q" class="input" placeholder="Ex. vulcanisateur, batterie…" aria-label="Que cherchez-vous ?">
                        <button class="btn btn-dark">Rechercher</button>
                    </div>
                </form>
            </div>

            {{-- Accès direct par métier --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($types as $type)
                    <a href="{{ route('search', ['type' => $type->value]) }}" class="group rounded-2xl bg-white/[0.07] p-4 ring-1 ring-white/10 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/[0.12]">
                        <span class="text-3xl">{{ $type->icon() }}</span>
                        <p class="mt-2 font-semibold">{{ $type->shortLabel() }}</p>
                        <p class="text-xs text-ink-300">{{ (int) ($stats['byType'][$type->slug] ?? 0) }} vérifié(s)</p>
                    </a>
                @endforeach
                <a href="{{ route('emergency') }}" class="rounded-2xl bg-red-600/90 p-4 transition hover:-translate-y-0.5 hover:bg-red-600">
                    <span class="text-3xl">🚨</span>
                    <p class="mt-2 font-semibold">Urgence</p>
                    <p class="text-xs text-red-100">Numéros utiles</p>
                </a>
            </div>
        </div>
    </section>

    {{-- Chiffres clés --}}
    <section class="relative z-10 mx-auto -mt-6 grid max-w-4xl grid-cols-2 gap-px overflow-hidden rounded-2xl bg-ink-100 shadow-lg ring-1 ring-ink-100 md:grid-cols-4">
        @foreach ([
            [$stats['providers'], 'pros vérifiés'],
            [$stats['cities'], 'ville(s) couvertes'],
            [$stats['night'], 'services de nuit'],
            feature('marketplace_enabled') ? [$stats['products'], 'pièces en vente'] : [count($types), 'métiers'],
        ] as [$value, $label])
            <div class="bg-white px-4 py-4 text-center">
                <p class="text-2xl font-extrabold text-ink-900">{{ number_format($value, 0, ',', ' ') }}</p>
                <p class="text-xs text-ink-500">{{ $label }}</p>
            </div>
        @endforeach
    </section>

    {{-- Problèmes fréquents → service --}}
    @if ($problems)
        <section class="mt-10">
            <h2 class="mb-3 text-lg font-bold">{{ feature('assist_enabled') ? 'Besoin d’un entretien ou d’une réparation ?' : 'Quel est votre problème ?' }}</h2>
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2">
                @foreach ($problems as $slug => $label)
                    <a href="{{ route('search', ['service' => $slug]) }}" class="chip shrink-0 border border-ink-200 bg-white px-4 py-2.5 text-sm text-ink-700 shadow-sm hover:border-brand-300 hover:text-brand-700">{{ $label }}</a>
                @endforeach
                <a href="{{ route('search', ['night' => 1]) }}" class="chip shrink-0 bg-ink-800 px-4 py-2.5 text-sm text-white">🌙 Ouvert la nuit</a>
            </div>
        </section>
    @endif

    {{-- Prestataires à la une / les mieux notés --}}
    @if ($featured->isNotEmpty())
        <section class="mt-10">
            <div class="mb-3 flex items-end justify-between">
                <div>
                    <h2 class="text-lg font-bold">{{ feature('featured_enabled') && $featured->contains('is_featured', true) ? 'À la une' : 'Les mieux notés' }}</h2>
                    <p class="text-sm text-ink-500">Professionnels vérifiés par l'équipe {{ setting('site_name') }}</p>
                </div>
                <a href="{{ route('search') }}" class="text-sm font-medium text-brand-600">Tout voir →</a>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($featured as $provider)
                    <x-provider-card :provider="$provider" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Électrique et carnet d'entretien --}}
    @if (feature('electric_page_enabled') || feature('logbook_enabled'))
        <section @class(['mt-12 grid gap-4', 'lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]' => feature('electric_page_enabled') && feature('logbook_enabled')])>
            @if (feature('electric_page_enabled'))
                <a href="{{ route('electric') }}" class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-800 to-ink-900 p-6 text-white md:p-8">
                    <div class="pointer-events-none absolute -right-10 -bottom-16 size-64 rounded-full bg-emerald-400/20 blur-2xl"></div>
                    <p class="text-xs font-bold tracking-widest text-emerald-300 uppercase">Nouveau</p>
                    <h2 class="mt-1 text-2xl font-extrabold md:text-3xl">⚡ Moto ou voiture électrique ?</h2>
                    <p class="mt-2 max-w-lg text-sm text-white/80">Réparateurs spécialisés vérifiés, points de recharge et d'échange de batterie, pannes fréquentes et bons gestes : tout est au même endroit.</p>
                    <div class="mt-4 flex flex-wrap items-center gap-5 text-sm">
                        <p><span class="block text-2xl font-extrabold">{{ $stats['electric'] }}</span>spécialiste(s)</p>
                        <p><span class="block text-2xl font-extrabold">{{ $stats['chargePoints'] }}</span>point(s) de recharge</p>
                        <span class="btn ml-auto bg-white text-ink-900 group-hover:bg-emerald-50">Découvrir →</span>
                    </div>
                </a>
            @endif
            @if (feature('logbook_enabled') && (! auth()->check() || auth()->user()->isClient()))
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-ink-100 md:p-8">
                    <h2 class="text-xl font-extrabold">🛠️ Ne ratez plus une vidange</h2>
                    <p class="mt-2 text-sm text-ink-500">Carnet d'entretien gratuit pour votre moto ou votre voiture : rappels de vidange, d'assurance et de visite technique, et l'historique de vos réparations.</p>
                    <ul class="mt-3 space-y-1 text-sm">
                        <li>✓ Rappel 7 jours avant l'échéance</li>
                        <li>✓ Un entretien noté en 10 secondes</li>
                        <li>✓ Ce que vous avez dépensé, par an</li>
                    </ul>
                    <a href="{{ auth()->check() ? route('logbook.index') : route('register') }}" class="btn btn-primary mt-4">{{ auth()->check() ? 'Ouvrir mon carnet' : 'Créer mon carnet gratuit' }}</a>
                </div>
            @endif
        </section>
    @endif

    {{-- Marketplace (désactivable dans l'admin) --}}
    @if (feature('marketplace_enabled'))
        <section class="mt-12 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-ink-100 md:p-8">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-bold tracking-widest text-brand-600 uppercase">Marketplace</p>
                    <h2 class="text-2xl font-extrabold">Pièces détachées près de chez vous</h2>
                    <p class="text-sm text-ink-500">Boutiques vérifiées, prix affichés, contact direct.</p>
                </div>
                <a href="{{ route('products.index') }}" class="btn btn-dark btn-sm">Voir toutes les pièces</a>
            </div>
            <form action="{{ route('products.index') }}" class="mb-4 flex gap-2">
                <input name="q" class="input" placeholder="Ex. batterie moto, plaquette Corolla…" aria-label="Rechercher une pièce">
                <button class="btn btn-primary">Chercher</button>
            </form>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-7">
                @foreach ($partCategories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" class="flex flex-col items-center gap-1 rounded-2xl bg-ink-50 px-2 py-3 text-center hover:bg-brand-50">
                        <span class="text-2xl">{{ $category->icon }}</span>
                        <span class="text-xs leading-tight font-medium">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
            @if ($latestProducts->isNotEmpty())
                <div class="-mx-5 mt-5 flex gap-3 overflow-x-auto px-5 pb-2 md:-mx-8 md:px-8">
                    @foreach ($latestProducts as $product)
                        <div class="w-40 shrink-0 sm:w-48"><x-product-card :product="$product" /></div>
                    @endforeach
                </div>
            @endif
            @if (feature('part_requests_enabled') && (! auth()->check() || auth()->user()->isClient()))
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-brand-50 p-4">
                    <p class="text-sm text-brand-900"><strong>Pièce introuvable ?</strong> Décrivez-la : les boutiques de votre ville vous envoient leur prix.</p>
                    <a href="{{ route('part-requests.create') }}" class="btn btn-primary btn-sm">Demander une pièce</a>
                </div>
            @endif
        </section>
    @endif

    {{-- Confiance --}}
    <section class="mt-12 grid gap-3 md:grid-cols-3">
        @foreach ([
            ['✓', 'Prestataires vérifiés', 'Chaque fiche est contrôlée avant d\'apparaître ; les badges confirment l\'identité, l\'entreprise et l\'adresse.'],
            ['★', 'Avis de vrais clients', 'Un seul avis par client et par prestataire, modéré pour éviter les faux avis.'],
            ['📞', 'Contact direct, sans frais', 'Appel ou WhatsApp en un geste, sans inscription. Vous payez le professionnel directement.'],
        ] as [$icon, $title, $text])
            <div class="flex gap-4 rounded-2xl p-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-ink-800 text-lg text-white">{{ $icon }}</span>
                <div>
                    <h3 class="font-semibold">{{ $title }}</h3>
                    <p class="mt-0.5 text-sm text-ink-500">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Espace professionnels --}}
    <section class="mt-10 overflow-hidden rounded-3xl bg-gradient-to-br from-brand-500 to-brand-700 p-6 text-white md:p-10">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] lg:items-center">
            <div>
                <h2 class="text-2xl font-extrabold md:text-3xl">Vous êtes mécanicien, garagiste, dépanneur ou vendeur de pièces ?</h2>
                <p class="mt-2 text-brand-100">Recevez des appels de clients près de chez vous et gérez votre activité au même endroit.</p>
                <ul class="mt-4 space-y-1.5 text-sm">
                    <li>✓ Fiche vérifiée, visible sur la carte</li>
                    @if (feature('shop_management_enabled')) <li>✓ Stock, factures, dépenses et bénéfices sur votre téléphone</li> @endif
                    @if (feature('marketplace_enabled')) <li>✓ Catalogue de pièces et demandes de clients</li> @endif
                </ul>
                <a href="{{ route('register', ['type' => 'pro']) }}" class="btn mt-5 bg-white px-5 py-3 text-brand-700 hover:bg-brand-50">Inscrire mon activité {{ $plans->isEmpty() ? 'gratuitement' : '' }}</a>
            </div>
            @if ($plans->isNotEmpty())
                <div @class(['grid gap-3', 'sm:grid-cols-3' => $plans->count() >= 3, 'sm:grid-cols-2' => $plans->count() < 3])>
                    @foreach ($plans as $plan)
                        <div @class(['rounded-2xl p-4', 'bg-white text-ink-900 shadow-xl' => $plan->is_highlighted, 'bg-white/10 ring-1 ring-white/20' => ! $plan->is_highlighted])>
                            <p class="font-bold">{{ $plan->name }}</p>
                            <p class="text-xl font-extrabold">{{ $plan->priceLabel() }}</p>
                            <p @class(['mt-1 text-xs', 'text-ink-500' => $plan->is_highlighted, 'text-brand-100' => ! $plan->is_highlighted])>{{ $plan->description }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
