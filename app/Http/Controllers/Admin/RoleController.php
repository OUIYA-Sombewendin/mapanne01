<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Rôles de l'équipe d'administration et leurs permissions. */
class RoleController extends Controller
{
    /** Permissions donnant accès à des actions sensibles (signalées dans la liste des rôles). */
    public const SENSITIVE = ['roles.manage', 'settings.manage', 'backups.manage', 'users.impersonate', 'data.import'];

    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), ['name', 'permissions', 'users', 'sensitive'], true) ? $request->query('sort') : null;
        $desc = $request->query('dir') === 'desc';
        $total = count(Permissions::all());

        $roles = Role::withCount('users')->with('lock')->orderByDesc('is_system')->orderBy('name')->get()
            ->each(function (Role $role) use ($total) {
                $role->permissions_count = $role->isSuperAdmin() ? $total : count(array_intersect($role->permissions ?? [], array_keys(Permissions::all())));
                $role->sensitive = $role->isSuperAdmin() || array_intersect($role->permissions ?? [], self::SENSITIVE) !== [];
            });
        if ($sort) {
            $roles = $roles->sortBy(fn (Role $r) => match ($sort) {
                'name' => mb_strtolower($r->name),
                'permissions' => $r->permissions_count,
                'users' => $r->users_count,
                'sensitive' => (int) $r->sensitive,
            }, SORT_REGULAR, $desc)->values();
        }

        return view('admin.roles.index', [
            'roles' => $roles,
            'totalPermissions' => $total,
            'sort' => $sort,
            'desc' => $desc,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', ['role' => new Role(['permissions' => [], 'color' => 'gray']), 'groups' => Permissions::groups()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $role = Role::create($data + ['slug' => $this->uniqueSlug($data['name'])]);
        ActivityLog::record('role.created', $role, ['permissions' => $role->permissions]);

        return redirect()->route('admin.roles.index')->with('success', "Rôle « {$role->name} » créé.");
    }

    /** Le rôle système peut être renommé, mais garde toujours toutes les permissions. */
    public function edit(Role $role): View
    {
        return view('admin.roles.form', ['role' => $role, 'groups' => Permissions::groups()]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);
        if ($role->is_system) {
            $data['permissions'] = ['*'];
        }
        $role->update($data);
        ActivityLog::record('role.updated', $role, ['permissions' => $role->permissions]);

        return redirect()->route('admin.roles.index')->with('success', 'Rôle mis à jour.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403);
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Ce rôle est attribué à des administrateurs : changez-leur de rôle d\'abord.']);
        }
        ActivityLog::record('role.deleted', null, ['rôle' => $role->name]);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Rôle supprimé.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role)],
            'color' => ['nullable', Rule::in(array_keys(Role::COLORS))],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(array_keys(Permissions::all()))],
        ]);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        $data['color'] ??= $role?->color ?? 'gray';

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
