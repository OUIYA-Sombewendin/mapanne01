@php
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ');
    $hour = now()->hour;
    $alertTone = ['red' => 'border-red-200 bg-red-50 text-red-900', 'amber' => 'border-amber-200 bg-amber-50 text-amber-900', 'brand' => 'border-brand-200 bg-brand-50 text-brand-900'];
    $shortcuts = array_values(array_filter([
        [route('search'), '🔧', 'Trouver un pro', 'Mécanicien, garage, dépanneur', true],
        [route('products.index'), '⚙️', 'Pièces détachées', 'Prix et boutiques près de vous', feature('marketplace_enabled')],
        [route('search', ['type' => 'station']), '⛽', 'Stations', 'Carburant et services', true],
        [route('part-requests.create'), '📣', 'Demander une pièce', 'Les boutiques vous répondent', feature('marketplace_enabled') && feature('part_requests_enabled')],
        [route('electric'), '⚡', 'Électrique', 'Recharge et spécialistes', feature('electric_page_enabled')],
        [route('emergency'), '☎️', 'Numéros d\'urgence', 'Police, pompiers, SAMU', true],
    ], fn ($s) => $s[4]));
@endphp
<x-layouts.app title="Mon espace">
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold">{{ $hour < 18 ? 'Bonjour' : 'Bonsoir' }} {{ $firstName }} 👋</h1>
        <p class="text-sm text-ink-500">Tout ce dont vous avez besoin pour votre véhicule, au même endroit.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            {{-- Dépannage : suivi en cours ou bouton d'urgence --}}
            @if ($activeAssist)
                <a href="{{ route('assist.show', $activeAssist) }}" class="on-color flex items-center gap-4 rounded-2xl bg-red-600 p-5 text-white shadow-md transition hover:bg-red-700">
                    <span class="text-4xl">🚨</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-extrabold">Dépannage en cours</span>
                        <span class="block text-sm text-white/90">{{ $activeAssist->problemLabel() }} · {{ $activeAssist->statusLabel() }}{{ $activeAssist->provider ? ' · '.$activeAssist->provider->business_name : '' }}</span>
                    </span>
                    <span class="rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold">Suivre →</span>
                </a>
            @elseif (feature('assist_enabled'))
                <section class="on-color rounded-2xl bg-gradient-to-br from-red-600 to-red-700 p-5 text-white shadow-md">
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-4xl">🚨</span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg font-extrabold">En panne maintenant ?</h2>
                            <p class="text-sm text-white/90">Les pros disponibles autour de vous sont prévenus et vous rappellent.</p>
                        </div>
                        <a href="{{ route('assist.create') }}" class="btn bg-white text-red-700 hover:bg-red-50">Demander de l'aide</a>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach (\App\Support\AssistProblems::all() as $key => $problem)
                            @continue($loop->index >= 5)
                            <a href="{{ route('assist.create', ['problem' => $key]) }}" class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold hover:bg-white/25">{{ $problem['icon'] ?? '' }} {{ $problem['label'] ?? $key }}</a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Raccourcis --}}
            <section>
                <h2 class="mb-3 font-bold">Que voulez-vous faire ?</h2>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    @foreach ($shortcuts as [$url, $icon, $label, $hint])
                        <a href="{{ $url }}" class="card flex flex-col gap-1 p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                            <span class="text-2xl">{{ $icon }}</span>
                            <span class="font-semibold">{{ $label }}</span>
                            <span class="text-xs text-ink-500">{{ $hint }}</span>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Recherche par problème --}}
            <section class="card p-5">
                <h2 class="mb-1 font-bold">Décrivez votre problème</h2>
                <p class="mb-3 text-sm text-ink-500">On vous montre les pros qui savent le régler, près de vous.</p>
                <form action="{{ route('search') }}" class="flex gap-2">
                    <input name="q" class="input flex-1" placeholder="Ex : crevaison, ma voiture chauffe, phare cassé…" aria-label="Votre problème">
                    <button class="btn btn-primary">Chercher</button>
                </form>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($problems as $key => $p)
                        <a href="{{ route('search', ['problem' => $key]) }}" class="chip bg-ink-100 text-ink-700 hover:bg-ink-200">{{ $p[1] }} {{ $p[0] }}</a>
                    @endforeach
                </div>
            </section>

            {{-- Véhicules --}}
            <section class="card p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-bold">🚗 Mes véhicules</h2>
                    <a href="{{ route('account.vehicles') }}" class="text-sm font-semibold text-accent-700">Gérer</a>
                </div>
                @forelse ($user->vehicles as $vehicle)
                    @php($next = $vehicle->reminders->sortBy('next_due_on')->first())
                    <a href="{{ feature('logbook_enabled') ? route('logbook.show', $vehicle) : route('account.vehicles') }}" class="flex items-center gap-3 border-t border-ink-50 py-2.5 first:border-0">
                        <span class="text-2xl">{{ $vehicle->icon() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $vehicle->title() }}</span>
                            <span class="block text-xs text-ink-500">{{ $vehicle->type->label() }} · {{ $vehicle->energyLabel() }}</span>
                        </span>
                        @if ($next) <span class="chip {{ $next->stateClass() }}">{{ $next->icon() }} {{ $next->dueLabel() }}</span> @endif
                    </a>
                @empty
                    <p class="text-sm text-ink-500">Ajoutez votre moto ou votre voiture : les recherches de pièces s'adaptent et vous recevez les rappels d'entretien.</p>
                    <a href="{{ route('account.vehicles') }}" class="btn btn-primary btn-sm mt-3">+ Ajouter un véhicule</a>
                @endforelse
            </section>
        </div>

        <aside class="space-y-5">
            {{-- À suivre --}}
            <section class="card p-5">
                <h2 class="mb-3 font-bold">🔔 À suivre</h2>
                <div class="space-y-2">
                    @forelse ($alerts as [$label, $detail, $url, $tone])
                        <a href="{{ $url }}" class="block rounded-xl border px-3 py-2 text-sm {{ $alertTone[$tone] ?? $alertTone['amber'] }}">
                            <span class="font-semibold">{{ $label }}</span>
                            @if ($detail) <span class="block truncate text-xs opacity-80">{{ $detail }}</span> @endif
                        </a>
                    @empty
                        <p class="text-sm text-ink-500">Rien à signaler pour le moment. 👌</p>
                    @endforelse
                </div>
            </section>

            {{-- Favoris : appel en un geste --}}
            <section class="card p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-bold">❤️ Mes favoris</h2>
                    @if ($favorites->isNotEmpty()) <a href="{{ route('account.favorites') }}" class="text-sm font-semibold text-accent-700">Tout voir</a> @endif
                </div>
                @forelse ($favorites as $fav)
                    <div class="flex items-center justify-between gap-2 border-t border-ink-50 py-2 text-sm first:border-0">
                        <a href="{{ route('providers.show', $fav) }}" class="min-w-0 truncate font-medium hover:text-brand-600">{{ $fav->type->icon() }} {{ $fav->business_name }}</a>
                        <a href="{{ $fav->callUrl() }}" class="btn btn-primary btn-sm shrink-0">Appeler</a>
                    </div>
                @empty
                    <p class="text-sm text-ink-500">Touchez ❤️ sur la fiche d'un pro pour le retrouver ici en cas de panne.</p>
                @endforelse
            </section>

            @if (feature('marketplace_enabled') && feature('part_requests_enabled'))
                <section class="card p-5">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="font-bold">📣 Demandes de pièces</h2>
                        <a href="{{ route('part-requests.create') }}" class="text-sm font-semibold text-accent-700">+ Nouvelle</a>
                    </div>
                    @forelse ($requests as $r)
                        <a href="{{ route('part-requests.show', $r) }}" class="flex items-center justify-between gap-2 border-t border-ink-50 py-2 text-sm first:border-0">
                            <span class="min-w-0 truncate">{{ $r->title }}</span>
                            <span class="chip shrink-0 {{ $r->offers_count ? 'bg-brand-100 text-brand-800' : 'bg-ink-100 text-ink-500' }}">{{ $r->offers_count }} offre(s)</span>
                        </a>
                    @empty
                        <p class="text-sm text-ink-500">Vous ne trouvez pas une pièce ? Décrivez-la, les boutiques vous répondent avec leur prix.</p>
                    @endforelse
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
