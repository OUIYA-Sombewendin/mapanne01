@php
    use App\Support\ProviderProfiles;
    $canManage = auth()->user()->can('referentials.manage');
    $tab = fn (?string $status) => route('admin.trades.show', ['type' => $type->slug, 'status' => $status] + request()->except(['status', 'page']));
@endphp
<x-layouts.admin :title="$type->name">
    {{-- Bandeau du métier --}}
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-lg sm:px-10">
        <div class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/5"></div>
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <span class="flex size-16 items-center justify-center rounded-2xl bg-white/95 text-4xl shadow">{{ $type->icon() }}</span>
                <div>
                    <p class="text-sm font-semibold tracking-wide text-white/70 uppercase">Espace métier</p>
                    <h1 class="text-3xl font-extrabold">{{ $type->name }}</h1>
                    <p class="mt-1 text-white/80">{{ $profile ? 'Fiche métier : '.ProviderProfiles::options()[$kind] : 'Formulaire général' }} · {{ count($type->modules ?? []) }} module(s) actif(s){{ $type->is_active ? '' : ' · métier désactivé' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($canManage)
                    <a href="{{ route('admin.forms.index', ['type' => $type->slug]) }}" class="btn bg-white text-ink-900 hover:bg-white/90"><x-icon name="document" /> Formulaire</a>
                    <a href="{{ route('admin.types.index') }}" class="btn border border-white/30 text-white hover:bg-white/10"><x-icon name="settings" /> Nom, icône, ordre</a>
                @endif
            </div>
        </div>
    </section>

    {{-- Chiffres --}}
    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['providers', $stats['total'], 'Fiches au total', null],
            ['clock', $counts['pending'] ?? 0, 'En attente', 'pending'],
            ['shield', $counts['approved'] ?? 0, 'Validées', 'approved'],
            ['flag', ($counts['suspended'] ?? 0) + ($counts['rejected'] ?? 0), 'Suspendues ou refusées', 'suspended'],
            ['star', $stats['rating'] ? number_format($stats['rating'], 1, ',', '').' / 5' : '—', 'Note moyenne', null],
            ['card', $stats['subscribers'], 'Abonnements actifs', null],
        ] as [$icon, $value, $label, $status])
            <a href="{{ $tab($status) }}" class="card border-t-4 border-accent-600 p-4 text-center transition hover:-translate-y-0.5">
                <span class="mx-auto flex size-11 items-center justify-center rounded-xl bg-accent-50 text-accent-700"><x-icon :name="$icon" class="size-6" /></span>
                <p class="mt-2 text-2xl font-extrabold">{{ $value }}</p>
                <p class="text-sm font-semibold">{{ $label }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_380px]">
        {{-- Fiches du métier --}}
        <section class="min-w-0">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h2 class="mr-2 text-xl font-extrabold" style="color: var(--heading)">Les {{ mb_strtolower($type->shortLabel()) }}s</h2>
                <span class="text-sm text-ink-500">+{{ $stats['new'] }} en 30 jours</span>
            </div>
            <div class="mb-3 flex flex-wrap gap-2">
                <a href="{{ $tab(null) }}" @class(['chip px-3 py-1.5 text-sm', 'bg-accent-700 text-white' => ! request('status'), 'bg-white text-ink-600 ring-1 ring-ink-200' => request('status')])>Toutes <span class="opacity-70">{{ $stats['total'] }}</span></a>
                @foreach (\App\Enums\ProviderStatus::cases() as $s)
                    <a href="{{ $tab($s->value) }}" @class(['chip px-3 py-1.5 text-sm', 'bg-accent-700 text-white' => request('status') === $s->value, 'bg-white text-ink-600 ring-1 ring-ink-200' => request('status') !== $s->value])>{{ $s->label() }} <span class="opacity-70">{{ $counts[$s->value] ?? 0 }}</span></a>
                @endforeach
            </div>

            <x-filter-bar :keys="['q', 'city', 'night', 'featured']" :keep="['status']">
                @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-56" placeholder="Nom, email ou téléphone">
                <select name="city" class="input-sm">
                    <option value="">Toutes les villes</option>
                    @foreach ($cities as $c) <option value="{{ $c->id }}" @selected(request('city') == $c->id)>{{ $c->name }}</option> @endforeach
                </select>
                @include('admin._flag', ['name' => 'night', 'label' => 'Nuit'])
                @include('admin._flag', ['name' => 'featured', 'label' => 'À la une'])
            </x-filter-bar>

            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr>
                        <x-sort-th column="name" :label="ProviderProfiles::copy($kind)['name']" />
                        <x-sort-th column="city" label="Ville" />
                        @foreach ($columns as $column)
                            <th>{{ $profile['fields'][$column]['label'] }}</th>
                        @endforeach
                        <x-sort-th column="rating" label="Note" align="center" />
                        <x-sort-th column="status" label="Statut" />
                        <x-sort-th column="created" label="Inscrit" />
                        <th></th>
                    </tr></thead>
                    <tbody>
                        @forelse ($providers as $p)
                            <tr>
                                <td class="font-semibold">{{ $p->business_name }}
                                    @if ($p->is_featured) <span title="À la une">⭐</span> @endif
                                    @if (! $p->user->is_active) <span class="chip bg-red-100 text-red-700">compte suspendu</span> @endif
                                    <span class="block text-xs font-normal text-ink-400">{{ \App\Support\Phone::format($p->phone) }}</span>
                                </td>
                                <td>{{ $p->city?->name }}</td>
                                @foreach ($columns as $column)
                                    <td class="max-w-48 text-sm text-ink-600">{{ ProviderProfiles::cell($kind, $column, $p->{$profile['relation']}) }}</td>
                                @endforeach
                                <td class="text-center whitespace-nowrap">{{ $p->reviews_count ? '★ '.number_format($p->rating_avg, 1, ',', '') : '—' }}</td>
                                <td><span class="chip {{ $p->status->color() }}">{{ $p->status->label() }}</span></td>
                                <td class="whitespace-nowrap text-ink-500">{{ $p->created_at->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap"><a href="{{ route('admin.providers.show', $p) }}" class="btn btn-ghost btn-sm">Ouvrir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 6 + count($columns) }}" class="py-6 text-center text-ink-400">Aucune fiche pour ce métier.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $providers->links() }}</div>
        </section>

        <aside class="space-y-6">
            {{-- Modules --}}
            <form method="POST" action="{{ route('admin.trades.modules', $type) }}" class="card p-6" x-data="{ on: @js($type->modules ?? []), requires: @js(collect($modules)->map(fn ($m) => $m['requires'] ?? [])) }">
                @csrf @method('PUT')
                @include('pro._section-head', ['icon' => 'layers', 'title' => 'Modules du métier', 'text' => 'Ce que les '.mb_strtolower($type->shortLabel()).'s voient dans leur espace pro. Fiche, horaires, photos et avis sont toujours inclus.'])
                <div class="space-y-2">
                    @foreach ($modules as $key => $module)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-ink-100 px-3 py-2.5 transition has-checked:border-accent-300 has-checked:bg-accent-50"
                               :class="(requires[@js($key)] ?? []).some(r => ! on.includes(r)) && 'opacity-50'">
                            <input type="checkbox" name="modules[]" value="{{ $key }}" x-model="on" class="checkbox mt-0.5" @disabled(! $canManage)>
                            <span class="min-w-0">
                                <span class="flex items-center gap-2 font-semibold"><x-icon :name="$module['icon']" class="size-4 text-accent-600" /> {{ $module['label'] }}</span>
                                <span class="block text-xs text-ink-500">{{ $module['help'] }}
                                    @if (! empty($module['requires'])) <span class="font-medium">Nécessite : {{ collect($module['requires'])->map(fn ($r) => $modules[$r]['label'])->implode(', ') }}.</span> @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @if ($canManage)
                    <button class="btn btn-primary mt-4 w-full">Enregistrer les modules</button>
                @endif
            </form>

            {{-- Formulaire --}}
            <section class="card p-6">
                @include('pro._section-head', ['icon' => 'document', 'title' => 'Formulaire de la fiche', 'text' => $profile ? count($profile['fields']).' champ(s) du métier, plus les champs ajoutés ici.' : 'Formulaire général, plus les champs ajoutés ici.'])
                <ul class="space-y-1.5 text-sm">
                    @forelse ($fields as $field)
                        <li class="flex items-center justify-between gap-2"><span>{{ $field->label }}</span>
                            <span @class(['chip', 'bg-emerald-100 text-emerald-800' => $field->is_active, 'bg-ink-100 text-ink-500' => ! $field->is_active])>{{ $field->is_active ? 'actif' : 'masqué' }}</span></li>
                    @empty
                        <li class="text-ink-400">Aucun champ ajouté pour ce métier.</li>
                    @endforelse
                </ul>
                @if ($canManage)
                    <a href="{{ route('admin.forms.index', ['type' => $type->slug]) }}" class="btn btn-ghost btn-sm mt-3"><x-icon name="plus" /> Ajouter ou modifier des champs</a>
                @endif
            </section>

            {{-- Offres --}}
            <section class="card p-6">
                @include('pro._section-head', ['icon' => 'card', 'title' => 'Offres d\'abonnement', 'text' => 'Offres proposées à ce métier (réservées à lui ou communes à tous).'])
                <ul class="space-y-1.5 text-sm">
                    @forelse ($plans as $plan)
                        <li class="flex items-center justify-between gap-2">
                            <span>{{ $plan->name }} <span class="text-xs text-ink-400">{{ empty($plan->provider_types) ? '· tous les métiers' : '· réservée' }}</span></span>
                            <span class="font-semibold whitespace-nowrap">{{ $plan->price ? number_format($plan->price, 0, ',', ' ').' F' : 'Gratuit' }}</span>
                        </li>
                    @empty
                        <li class="text-ink-400">Aucune offre.</li>
                    @endforelse
                </ul>
                @can('subscriptions.manage')
                    <a href="{{ route('admin.plans.create') }}" class="btn btn-ghost btn-sm mt-3"><x-icon name="plus" /> Créer une offre</a>
                @endcan
            </section>
        </aside>
    </div>
</x-layouts.admin>
