<x-layouts.admin title="Utilisateurs · Admin">
    <h1 class="mb-4 text-2xl font-extrabold">Utilisateurs</h1>

    <x-filter-bar :keys="['q', 'role', 'admin_role', 'active', 'verified', 'city', 'login', 'from']">
        <input type="search" name="q" value="{{ request('q') }}" class="input-sm w-56" placeholder="Nom, téléphone, email">
        <select name="role" class="input-sm">
            <option value="">Tous les comptes</option>
            @foreach ($roles as $r)
                <option value="{{ $r->value }}" @selected($role === $r)>{{ $r->label() }}</option>
            @endforeach
        </select>
        <select name="admin_role" class="input-sm">
            <option value="">Rôle admin : tous</option>
            @foreach ($adminRoles as $ar) <option value="{{ $ar->id }}" @selected(request('admin_role') == $ar->id)>{{ $ar->name }}</option> @endforeach
        </select>
        <select name="active" class="input-sm">
            <option value="">Statut : tous</option>
            <option value="1" @selected(request('active') === '1')>Actifs</option>
            <option value="0" @selected(request('active') === '0')>Bloqués</option>
        </select>
        @include('admin._flag', ['name' => 'verified', 'label' => 'Email confirmé'])
        <select name="city" class="input-sm">
            <option value="">Toutes les villes</option>
            @foreach ($cities as $c) <option value="{{ $c->id }}" @selected(request('city') == $c->id)>{{ $c->name }}</option> @endforeach
        </select>
        <select name="login" class="input-sm">
            <option value="">Connexion : toutes</option>
            <option value="30d" @selected(request('login') === '30d')>Connectés ces 30 jours</option>
            <option value="never" @selected(request('login') === 'never')>Jamais connectés</option>
        </select>
        <label class="flex items-center gap-1 text-sm text-ink-500">Inscrits depuis <input type="date" name="from" value="{{ request('from') }}" class="input-sm"></label>
    </x-filter-bar>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr>
                <x-sort-th column="name" label="Nom" />
                <x-sort-th column="email" label="Email / téléphone" />
                <x-sort-th column="role" label="Rôle" />
                <x-sort-th column="created" label="Inscrit" class="hidden 2xl:table-cell" />
                <x-sort-th column="login" label="Dernière connexion" class="hidden xl:table-cell" />
                <x-sort-th column="active" label="Statut" />
                <th>Actions</th>
            </tr></thead>
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
