@php
    $assist = $provider->hasModule('assist');
    $tiles = array_values(array_filter([
        ['views', 'Vues de la fiche', 'chart'],
        ['calls', 'Appels', 'phone'],
        ['whatsapp', 'Messages WhatsApp', 'transfer'],
        ['directions', 'Itinéraires demandés', 'map'],
        $assist ? ['assist_received', 'Dépannages reçus', 'flag'] : null,
        $assist ? ['assist_done', 'Dépannages réalisés', 'shield'] : null,
        ['reviews', 'Nouveaux avis', 'star'],
    ]));
    $max = max(1, collect($series)->max('views'), collect($series)->max('contacts'));
    $contacts = $current['calls'] + $current['whatsapp'] + $current['directions'];
    $rate = $current['views'] ? round($contacts / $current['views'] * 100) : null;

    // Heures et jours d'affluence
    $hourMax = max(1, collect($hours)->max(fn ($h) => $h['views'] + $h['contacts']));
    $peak = collect($hours)->sortByDesc(fn ($h) => $h['views'] + $h['contacts'])->first();
    $dayNames = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
    $dayMax = max(1, collect($weekdays)->max(fn ($d) => $d['views'] + $d['contacts']));
    $sourceTotal = max(1, array_sum($sources));
@endphp
<x-layouts.pro title="Statistiques">
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-6 text-white shadow-lg sm:px-8">
        <div class="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-white/5"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold sm:text-3xl">📊 Statistiques de la fiche</h1>
                <p class="mt-1 text-white/85">{{ $contacts }} contact(s) (appels, WhatsApp, itinéraires) grâce à {{ setting('site_name') }} sur les {{ $days }} derniers jours.
                    @if ($rate !== null) <strong class="text-white">{{ $rate }} %</strong> des visiteurs de la fiche vous contactent. @endif</p>
            </div>
            <div class="flex gap-1 rounded-full bg-white/15 p-1">
                @foreach ([7 => '7 jours', 30 => '30 jours', 90 => '3 mois'] as $d => $label)
                    <a href="{{ route('pro.stats', ['periode' => $d]) }}" @class(['rounded-full px-3 py-1 text-sm font-semibold', 'bg-white text-accent-800' => $days === $d, 'text-white/85 hover:bg-white/10' => $days !== $d])>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </section>

    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-{{ count($tiles) }}">
        @foreach ($tiles as [$key, $label, $icon])
            @php($diff = $current[$key] - $previous[$key])
            <div class="card border-t-4 border-accent-600 p-4 text-center">
                <span class="mx-auto flex size-11 items-center justify-center rounded-xl bg-accent-50 text-accent-700"><x-icon :name="$icon" class="size-6" /></span>
                <p class="mt-2 text-3xl font-extrabold">{{ $current[$key] }}</p>
                <p class="text-sm font-semibold">{{ $label }}</p>
                <p @class(['text-xs font-semibold', 'text-emerald-600' => $diff > 0, 'text-red-600' => $diff < 0, 'text-ink-400' => $diff === 0])>
                    {{ $diff > 0 ? '▲ +'.$diff : ($diff < 0 ? '▼ '.$diff : '=') }} <span class="font-normal text-ink-400">vs période précédente</span>
                </p>
            </div>
        @endforeach
    </div>

    {{-- Courbe quotidienne (barres simples, sans bibliothèque) --}}
    <section class="card mt-6 p-6">
        @include('pro._section-head', ['icon' => 'chart', 'title' => 'Jour par jour', 'text' => 'Vues de la fiche et contacts (appels, WhatsApp, itinéraires).'])
        <div class="flex h-48 items-end gap-[3px]" role="img" aria-label="Vues et contacts par jour">
            @foreach ($series as $point)
                <div class="group relative flex h-full flex-1 flex-col justify-end gap-[2px]">
                    <div class="rounded-t bg-accent-200" style="height: {{ round($point['views'] / $max * 100) }}%"></div>
                    <div class="rounded-t bg-brand-500" style="height: {{ round($point['contacts'] / $max * 100) }}%"></div>
                    <span class="pointer-events-none absolute -top-9 left-1/2 z-10 hidden -translate-x-1/2 rounded-lg bg-ink-900 px-2 py-1 text-xs whitespace-nowrap text-white group-hover:block">
                        {{ $point['day']->format('d/m') }} · {{ $point['views'] }} vue(s) · {{ $point['contacts'] }} contact(s)</span>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-xs text-ink-400">
            <span>{{ $series[0]['day']->format('d/m') }}</span>
            <span class="flex gap-4"><span><span class="mr-1 inline-block size-2.5 rounded bg-accent-200"></span>Vues</span><span><span class="mr-1 inline-block size-2.5 rounded bg-brand-500"></span>Contacts</span></span>
            <span>Aujourd'hui</span>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Heures d'affluence --}}
        <section class="card p-6">
            @include('pro._section-head', ['icon' => 'clock', 'title' => 'Heures de visite', 'text' => 'À quelle heure les clients regardent votre fiche et vous contactent.'])
            <div class="flex h-36 items-end gap-[2px]" role="img" aria-label="Visites par heure">
                @foreach ($hours as $h)
                    <div class="group relative flex h-full flex-1 flex-col justify-end gap-[1px]">
                        <div class="rounded-t bg-accent-200" style="height: {{ round($h['views'] / $hourMax * 100) }}%"></div>
                        <div class="rounded-t bg-brand-500" style="height: {{ round($h['contacts'] / $hourMax * 100) }}%"></div>
                        <span class="pointer-events-none absolute -top-9 left-1/2 z-10 hidden -translate-x-1/2 rounded-lg bg-ink-900 px-2 py-1 text-xs whitespace-nowrap text-white group-hover:block">{{ $h['hour'] }} h · {{ $h['views'] }} vue(s) · {{ $h['contacts'] }} contact(s)</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-ink-400"><span>0 h</span><span>6 h</span><span>12 h</span><span>18 h</span><span>23 h</span></div>
            @if ($peak && $peak['views'] + $peak['contacts'] > 0)
                <p class="mt-3 rounded-xl bg-accent-50 px-3 py-2 text-sm text-accent-900">💡 Le plus d'activité vers <strong>{{ $peak['hour'] }} h – {{ ($peak['hour'] + 1) % 24 }} h</strong> : soyez joignable à ce moment-là.</p>
            @endif
        </section>

        {{-- Jours de la semaine --}}
        <section class="card p-6">
            @include('pro._section-head', ['icon' => 'history', 'title' => 'Jours de la semaine', 'text' => 'Les jours où l’on vous cherche le plus.'])
            <div class="space-y-2">
                @foreach ($weekdays as $d)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-9 shrink-0 font-semibold text-ink-600">{{ $dayNames[$d['day']] }}</span>
                        <div class="flex h-5 flex-1 overflow-hidden rounded-full bg-ink-50">
                            <div class="bg-accent-300" style="width: {{ round($d['views'] / $dayMax * 100) }}%"></div>
                            <div class="bg-brand-500" style="width: {{ round($d['contacts'] / $dayMax * 100) }}%"></div>
                        </div>
                        <span class="w-24 shrink-0 text-right text-xs text-ink-500">{{ $d['views'] }} vue(s) · {{ $d['contacts'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Provenance des visites --}}
        <section class="card p-6">
            @include('pro._section-head', ['icon' => 'search', 'title' => 'D’où viennent vos visiteurs', 'text' => 'Comment les clients sont arrivés sur votre fiche.'])
            <div class="space-y-3">
                @foreach ($sources as $key => $count)
                    <div>
                        <div class="mb-1 flex justify-between text-sm"><span>{{ \App\Support\ProviderStats::SOURCES[$key] }}</span><span class="font-semibold">{{ $count }} <span class="font-normal text-ink-400">({{ round($count / $sourceTotal * 100) }} %)</span></span></div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-ink-50"><div class="h-full rounded-full bg-accent-500" style="width: {{ round($count / $sourceTotal * 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-ink-500">Une fiche complète (photos, horaires, services) remonte dans les résultats et sur la carte.</p>
        </section>

        <section class="card p-6">
            @include('pro._section-head', ['icon' => 'star', 'title' => 'Votre réputation', 'text' => 'Note moyenne et nombre d\'avis publiés.'])
            <p class="text-4xl font-extrabold">{{ $provider->reviews_count ? number_format($provider->rating_avg, 1, ',', '').' / 5' : '—' }}</p>
            <p class="text-sm text-ink-500">{{ $provider->reviews_count }} avis au total</p>
            <p class="mt-3 text-sm text-ink-600">Demandez à vos clients satisfaits de laisser un avis : c'est ce qui fait monter votre fiche dans les résultats.</p>
        </section>

        @if ($products->isNotEmpty())
            <section class="card p-6">
                @include('pro._section-head', ['icon' => 'cube', 'title' => 'Pièces les plus vues', 'text' => 'Depuis leur mise en ligne.'])
                <table class="data-table">
                    <thead><tr><th>Pièce</th><th class="text-right">Vues</th><th class="text-right">Contacts</th></tr></thead>
                    <tbody>
                        @foreach ($products as $p)
                            <tr><td>{{ $p->name }}</td><td class="text-right">{{ $p->views_count }}</td><td class="text-right">{{ $p->contacts_count }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>
</x-layouts.pro>
