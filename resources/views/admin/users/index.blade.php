<x-layouts.admin title="Utilisateurs · Admin">
    <h1 class="mb-4 text-2xl font-extrabold">Utilisateurs</h1>

    <form class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Nom, téléphone, email">
        <select name="role" class="input max-w-48">
            <option value="">Tous les rôles</option>
            @foreach ($roles as $r)
                <option value="{{ $r->value }}" @selected($role === $r)>{{ $r->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark">Filtrer</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Nom</th><th>Email / téléphone</th><th>Rôle</th><th class="hidden 2xl:table-cell">Inscrit</th><th class="hidden xl:table-cell">Dernière connexion</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td class="font-medium">{{ $u->name }}
                            @if ($u->provider) <a href="{{ route('admin.providers.show', $u->provider) }}" class="block text-xs text-brand-600">{{ $u->provider->business_name }}</a> @endif
                        </td>
                        <td class="max-w-56 break-all">{{ $u->email }}
                            @if ($u->hasVerifiedEmail()) <span class="text-emerald-600" title="Email confirmé">✓</span>
                            @else <span class="text-amber-600" title="Email non confirmé">⚠</span> @endif
                            <br><span class="text-xs text-ink-400">{{ $u->phone ? \App\Support\Phone::format($u->phone) : 'pas de téléphone' }}</span></td>
                        <td>{{ $u->role->label() }} @if ($u->adminRole) <span class="chip bg-ink-100 text-ink-700">{{ $u->adminRole->name }}</span> @endif</td>
                        <td class="hidden whitespace-nowrap text-ink-500 2xl:table-cell">{{ $u->created_at->format('d/m/Y') }}</td>
                        <td class="hidden whitespace-nowrap text-ink-500 xl:table-cell">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td><span @class(['chip', 'bg-emerald-100 text-emerald-800' => $u->is_active, 'bg-red-100 text-red-800' => ! $u->is_active])>{{ $u->is_active ? 'Actif' : 'Bloqué' }}</span></td>
                        <td class="min-w-60">
                            @if ($u->id !== auth()->id())
                                @php($locked = $u->isLocked())
                                <div class="flex flex-wrap items-center gap-1">
                                    @if (auth()->user()->can('users.impersonate') && ! $u->isAdmin() && $u->is_active)
                                        <form method="POST" action="{{ route('admin.users.impersonate', $u) }}" onsubmit="return confirm('Accéder au compte de {{ addslashes($u->name) }} ? Vous verrez son espace comme lui.')">@csrf
                                            <button class="btn btn-dark btn-sm" title="Se connecter à ce compte pour aider la personne">👁 Accéder</button>
                                        </form>
                                    @endif
                                    @if (! $locked && auth()->user()->can('users.manage'))
                                        <form method="POST" action="{{ route('admin.users.toggle', $u) }}" onsubmit="return confirm('{{ $u->is_active ? 'Bloquer ce compte ? La personne sera déconnectée immédiatement.' : 'Débloquer ce compte ?' }}')">
                                            @csrf
                                            <button @class(['btn btn-sm', 'btn-ghost text-red-600' => $u->is_active, 'btn-ghost text-emerald-700' => ! $u->is_active])>{{ $u->is_active ? '⛔ Bloquer' : '✓ Débloquer' }}</button>
                                        </form>
                                    @endif
                                    @if (! $locked && ! $u->hasVerifiedEmail() && auth()->user()->can('users.manage'))
                                        <form method="POST" action="{{ route('admin.users.verify-email', $u) }}">@csrf
                                            <button class="btn btn-ghost btn-sm">Confirmer l'email</button>
                                        </form>
                                    @endif
                                    <x-lock-button :model="$u" compact />
                                </div>
                                @if (! $locked && ! $u->isProvider() && auth()->user()->can('roles.manage'))
                                    <form method="POST" action="{{ route('admin.users.role', $u) }}" class="mt-1">
                                        @csrf
                                        <select name="admin_role_id" class="input w-full py-1 text-xs" onchange="if (confirm('Modifier les droits de {{ addslashes($u->name) }} ?')) this.form.submit(); else this.value = '{{ $u->admin_role_id }}'" aria-label="Rôle d'administration">
                                            <option value="">{{ $u->isAdmin() ? '— Retirer l’accès admin —' : 'Nommer admin…' }}</option>
                                            @foreach ($adminRoles as $ar)
                                                <option value="{{ $ar->id }}" @selected($u->admin_role_id === $ar->id)>{{ $ar->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>
