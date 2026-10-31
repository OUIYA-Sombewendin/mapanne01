@php
    $sortLink = fn ($key) => route('admin.roles.index', ['sort' => $key, 'dir' => $sort === $key && ! $desc ? 'desc' : 'asc']);
    $sortIcon = fn ($key) => $sort === $key ? ($desc ? '↓' : '↑') : '⇅';
@endphp
<x-layouts.admin title="Rôles et permissions">
    {{-- Bandeau --}}
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-lg sm:px-10">
        <div class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/5"></div>
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="flex items-center gap-3 text-2xl font-extrabold sm:text-4xl"><x-icon name="shield" class="size-9 sm:size-11" stroke-width="1.5" /> Rôles et permissions</h1>
                <span class="mt-3 block h-1 w-28 rounded-full bg-brand-500"></span>
                <p class="mt-4 text-white/80 sm:text-lg">Créez des rôles et choisissez précisément ce que chacun peut faire, sans toucher au code.</p>
            </div>
            <a href="{{ route('admin.roles.create') }}" class="btn shrink-0 bg-white px-5 py-3 text-base text-ink-900 shadow hover:bg-white/90"><x-icon name="plus" /> Nouveau rôle</a>
        </div>
    </section>

    {{-- Information --}}
    <div class="mt-6 flex gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sky-900">
        <svg class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
        <p>Le rôle <strong>{{ $roles->firstWhere('is_system', true)?->name ?? 'Super-administrateur' }}</strong> possède toujours toutes les permissions : il peut être renommé mais ni restreint ni supprimé.
            Un rôle encore attribué à des comptes ne peut pas être supprimé. Pour ajouter un membre à l'équipe, attribuez-lui un rôle depuis <a href="{{ route('admin.users.index') }}" class="font-semibold underline">Comptes</a>.</p>
    </div>

    {{-- Tableau des rôles --}}
    <div class="card mt-6 overflow-x-auto">
        <table class="w-full text-left text-[15px]">
            <thead class="bg-ink-50 text-xs font-bold tracking-wider text-ink-600 uppercase">
                <tr>
                    <th class="px-5 py-4"><a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-1">Rôle <span class="text-ink-400">{{ $sortIcon('name') }}</span></a></th>
                    <th class="px-4 py-4">Description</th>
                    <th class="px-4 py-4 text-center"><a href="{{ $sortLink('sensitive') }}" class="inline-flex items-center gap-1" title="Rôles, réglages, sauvegardes, import, accès aux comptes">Accès sensibles <span class="text-ink-400">{{ $sortIcon('sensitive') }}</span></a></th>
                    <th class="px-4 py-4 text-center"><a href="{{ $sortLink('permissions') }}" class="inline-flex items-center gap-1">Permissions <span class="text-ink-400">{{ $sortIcon('permissions') }}</span></a></th>
                    <th class="px-4 py-4 text-center"><a href="{{ $sortLink('users') }}" class="inline-flex items-center gap-1">Comptes <span class="text-ink-400">{{ $sortIcon('users') }}</span></a></th>
                    <th class="px-5 py-4">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100">
                @foreach ($roles as $role)
                    <tr @class(['align-middle', 'bg-ink-50/70' => $role->is_system])>
                        <td @class(['px-5 py-4 whitespace-nowrap', 'border-l-4 border-ink-800' => $role->is_system])>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md px-2.5 py-1 text-sm font-bold {{ $role->badgeClass() }}">{{ $role->name }}</span>
                                @if ($role->is_system) <span class="rounded-md border border-ink-200 bg-white px-2 py-0.5 text-sm font-semibold">Système</span> @endif
                                @if ($role->isLocked()) <span title="Verrouillé">🔒</span> @endif
                            </div>
                        </td>
                        <td class="min-w-60 max-w-md px-4 py-4 text-ink-600">{{ $role->description ?: '—' }}</td>
                        <td class="px-4 py-4 text-center">
                            @if ($role->sensitive)
                                <span class="inline-flex size-6 items-center justify-center rounded-full bg-emerald-600 text-white on-color" title="A accès à des actions sensibles">✓</span>
                            @else
                                <span class="inline-flex size-6 items-center justify-center rounded-full border-2 border-ink-300 text-ink-400" title="Aucun accès sensible">–</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-center whitespace-nowrap">
                            @if ($role->isSuperAdmin())
                                <span class="on-color rounded-md bg-emerald-600 px-2.5 py-1 text-sm font-bold text-white">Toutes</span>
                            @else
                                <span x-data="{ open: false }" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false" class="font-medium underline decoration-dotted underline-offset-4">{{ $role->permissions_count }} / {{ $totalPermissions }}</button>
                                    <span x-cloak x-show="open" class="absolute top-full left-1/2 z-20 mt-2 w-72 -translate-x-1/2 rounded-xl bg-white p-3 text-left text-sm shadow-xl ring-1 ring-ink-100">
                                        @forelse ($role->permissionLabels() as $label)
                                            <span class="block py-0.5"><span class="text-emerald-600">✓</span> {{ $label }}</span>
                                        @empty
                                            <span class="text-ink-400">Aucune permission.</span>
                                        @endforelse
                                    </span>
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-center">
                            <a href="{{ route('admin.users.index', ['admin_role' => $role->id]) }}" class="font-medium underline underline-offset-4 hover:text-brand-600" title="Voir les comptes">{{ $role->users_count }}</a>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                @unless ($role->isLocked())
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-ghost btn-sm">✏️ {{ $role->is_system ? 'Renommer' : 'Modifier' }}</a>
                                    @if (! $role->is_system && $role->users_count === 0)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Supprimer le rôle « {{ addslashes($role->name) }} » ?')">@csrf @method('DELETE')
                                            <button class="btn btn-ghost btn-sm text-red-600" title="Supprimer">🗑</button>
                                        </form>
                                    @endif
                                @endunless
                                <x-lock-button :model="$role" compact />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-ink-400">Cliquez sur le nombre de permissions pour voir le détail, sur le nombre de comptes pour voir les membres.</p>
</x-layouts.admin>
