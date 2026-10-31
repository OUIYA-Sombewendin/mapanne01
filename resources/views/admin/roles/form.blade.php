@php
    $selected = old('permissions', $role->permissions ?? []);
    $total = count(\App\Support\Permissions::all());
    $icons = \App\Support\Permissions::icons();
@endphp
<x-layouts.admin :title="$role->exists ? 'Modifier le rôle' : 'Nouveau rôle'">
    {{-- Bandeau --}}
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-lg sm:px-10">
        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-3 text-2xl font-extrabold sm:text-4xl"><x-icon name="shield" class="size-9 sm:size-11" stroke-width="1.5" /> {{ $role->exists ? $role->name : 'Nouveau rôle' }}</h1>
                <span class="mt-3 block h-1 w-28 rounded-full bg-brand-500"></span>
            </div>
            <a href="{{ route('admin.roles.index') }}" class="btn shrink-0 border border-white/30 px-5 py-3 text-base text-white hover:bg-white/10">← Retour aux rôles</a>
        </div>
    </section>

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="mt-6 space-y-6"
          x-data="roleForm({{ count(array_intersect($selected, array_keys(\App\Support\Permissions::all()))) }}, {{ $total }})">
        @csrf
        @if ($role->exists) @method('PUT') @endif

        {{-- Identité du rôle --}}
        <section class="card grid gap-5 p-6 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <x-field name="name" label="Nom du rôle" :value="$role->name" required placeholder="Ex. Modérateur, Comptable, Support…" />
            <div x-data="{ color: '{{ old('color', $role->color ?? 'gray') }}', label: @js(old('name', $role->name) ?: 'Aperçu'), classes: @js(collect(\App\Models\Role::COLORS)->map(fn ($c) => $c[1])) }"
                 x-init="document.getElementById('name').addEventListener('input', e => label = e.target.value || 'Aperçu')">
                <label for="color" class="field-label">Couleur du badge</label>
                <div class="flex items-center gap-3">
                    <select id="color" name="color" x-model="color" class="input">
                        @foreach (\App\Models\Role::COLORS as $key => [$label]) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                    <span class="shrink-0 rounded-md px-2.5 py-1 text-sm font-bold" :class="classes[color]" x-text="label"></span>
                </div>
            </div>
            <div class="md:col-span-2"><x-field name="description" label="Description (facultative)" :value="$role->description" placeholder="Ce que fait ce rôle au quotidien" /></div>
        </section>

        @if ($role->is_system)
            <p class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sky-900">Ce rôle système possède toujours toutes les permissions : seuls son nom, sa couleur et sa description se modifient.</p>
        @else
            {{-- Barre de permissions --}}
            <section class="card sticky top-[96px] z-10 flex flex-wrap items-center gap-4 p-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600"><x-icon name="shield" class="size-6" /></span>
                    <div>
                        <p class="font-bold">Permissions</p>
                        <p class="text-sm text-ink-500"><strong x-text="count">0</strong> sur {{ $total }} accordées</p>
                    </div>
                </div>
                <div class="h-2 min-w-24 flex-1 overflow-hidden rounded-full bg-ink-100"><div class="h-full rounded-full bg-brand-500 transition-all" :style="`width: ${count / total * 100}%`"></div></div>
                <div class="relative w-full sm:w-72">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                    <input type="search" x-model="search" class="input pl-9" placeholder="Chercher une permission…">
                </div>
                <div class="flex overflow-hidden rounded-xl ring-1 ring-ink-200">
                    <button type="button" @click="setAll(true)" class="px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">✓ Tout accorder</button>
                    <button type="button" @click="setAll(false)" class="border-l border-ink-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">✕ Tout retirer</button>
                </div>
            </section>

            {{-- Groupes de permissions --}}
            <section class="card divide-y divide-ink-100 overflow-hidden">
                @foreach ($groups as $group => $permissions)
                    <div class="grid md:grid-cols-[260px_minmax(0,1fr)]" data-group x-show="groupVisible($el)">
                        <div class="flex items-start justify-between gap-3 bg-ink-50 p-5 md:block">
                            <div class="flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-white text-ink-600 ring-1 ring-ink-100"><x-icon :name="$icons[$group] ?? 'settings'" /></span>
                                <h2 class="font-bold">{{ $group }}</h2>
                            </div>
                            <div class="flex items-center justify-between gap-3 md:mt-3">
                                <span class="rounded-lg bg-white px-2 py-0.5 text-sm font-bold ring-1 ring-ink-100"><span x-text="groupCount($el.closest('[data-group]'))">0</span> / {{ count($permissions) }}</span>
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" class="peer sr-only" :checked="groupFull($el.closest('[data-group]'))" @change="setGroup($el.closest('[data-group]'), $event.target.checked)">
                                    <span class="relative h-5 w-9 rounded-full bg-ink-200 transition peer-checked:bg-brand-500 after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
                                    Tout
                                </label>
                            </div>
                        </div>
                        <div class="grid gap-x-8 gap-y-4 p-5 sm:grid-cols-2">
                            @foreach ($permissions as $key => $label)
                                <label class="flex cursor-pointer items-start gap-3" data-perm="{{ mb_strtolower($label.' '.$key) }}" x-show="matches($el)">
                                    <input type="checkbox" name="permissions[]" value="{{ $key }}" class="peer sr-only" @checked(in_array($key, $selected, true)) @change="recount()">
                                    <span class="relative mt-0.5 h-5 w-9 shrink-0 rounded-full bg-ink-200 transition peer-checked:bg-brand-500 after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
                                    <span>
                                        <span class="block">{{ $label }}</span>
                                        <code class="text-xs text-ink-400">{{ $key }}</code>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>
            <p class="text-xs text-ink-400">Tous les administrateurs voient le tableau de bord. Les changements s'appliquent dès la page suivante.</p>
        @endif

        <div class="flex gap-3">
            <button class="btn btn-primary px-6">Enregistrer</button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</x-layouts.admin>
