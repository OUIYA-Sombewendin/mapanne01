<x-layouts.pro title="Équipe">
    <h1 class="mb-1 text-2xl font-extrabold">Équipe de {{ $provider->business_name }}</h1>
    <p class="mb-4 text-sm text-ink-500">Ajoutez vos employés : chacun a son propre compte et ne voit que ce que son rôle permet. Vous restez le seul propriétaire.</p>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Employé</th><th>Rôle</th><th>Dernière connexion</th><th></th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="font-semibold">{{ $provider->user->name }}<span class="block text-xs font-normal text-ink-400">{{ $provider->user->email }}</span></td>
                            <td><span class="chip bg-ink-800 text-white">Propriétaire</span></td>
                            <td class="text-ink-500">—</td><td></td>
                        </tr>
                        @forelse ($members as $m)
                            <tr>
                                <td class="font-semibold">{{ $m->name }}<span class="block text-xs font-normal text-ink-400">{{ $m->email }}{{ $m->phone ? ' · '.\App\Support\Phone::format($m->phone) : '' }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('pro.team.update', $m) }}">@csrf @method('PUT')
                                        <select name="role" class="input w-auto py-1 text-sm" onchange="this.form.submit()" aria-label="Rôle">
                                            @foreach ($roles as $key => [$label]) <option value="{{ $key }}" @selected($m->pivot->role === $key)>{{ $label }}</option> @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="whitespace-nowrap text-ink-500">{{ $m->last_login_at?->diffForHumans() ?? 'jamais' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('pro.team.destroy', $m) }}" onsubmit="return confirm('Retirer {{ addslashes($m->name) }} de l\'équipe ?')">@csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm text-red-600">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-5 text-center text-ink-400">Aucun employé pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <section class="card overflow-x-auto p-5">
                <h2 class="mb-3 font-bold">Ce que chaque rôle peut faire</h2>
                <table class="data-table">
                    <thead><tr><th>Zone</th><th class="text-center">Propriétaire</th>@foreach ($roles as [$label]) <th class="text-center">{{ $label }}</th> @endforeach</tr></thead>
                    <tbody>
                        @foreach ($areas as $area => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-center text-emerald-600">✓</td>
                                @foreach (array_keys($roles) as $role)
                                    <td class="text-center">{!! \App\Support\ShopTeam::allows($role, $area) ? '<span class="text-emerald-600">✓</span>' : '<span class="text-ink-300">–</span>' !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>

        <form method="POST" action="{{ route('pro.team.store') }}" class="card h-fit space-y-3 p-5 lg:sticky lg:top-20">
            @csrf
            <h2 class="font-bold">Ajouter un employé</h2>
            <p class="text-xs text-ink-500">{{ $members->count() }} / {{ $max }} employés.</p>
            <x-field name="name" label="Nom" placeholder="Ex. Issa Kaboré" />
            <x-field name="email" type="email" label="Email" required placeholder="employe@exemple.bf" />
            <x-field name="phone" type="tel" label="Téléphone (facultatif)" placeholder="70 00 00 00" />
            <div>
                <label class="field-label" for="role">Rôle</label>
                <select id="role" name="role" class="input">
                    @foreach ($roles as $key => [$label, $desc]) <option value="{{ $key }}" @selected(old('role', 'seller') === $key)>{{ $label }} — {{ $desc }}</option> @endforeach
                </select>
            </div>
            <x-field name="password" type="password" label="Mot de passe provisoire (facultatif)" autocomplete="new-password" hint="Vide : l'employé reçoit un email pour choisir son mot de passe." />
            <button class="btn btn-primary w-full">Ajouter à l'équipe</button>
        </form>
    </div>
</x-layouts.pro>
