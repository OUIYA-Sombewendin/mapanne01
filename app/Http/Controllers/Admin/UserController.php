<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = UserRole::tryFrom((string) $request->query('role'));
        $q = trim((string) $request->query('q'));

        return view('admin.users.index', [
            'users' => User::query()
                ->with(['provider:id,user_id,business_name,slug', 'adminRole:id,name', 'lock'])
                ->when($role, fn ($query) => $query->where('role', $role))
                ->when($request->query('admin_role'), fn ($query, $id) => $query->where('admin_role_id', $id))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->where('name', 'ilike', "%$q%")
                    ->orWhere('email', 'ilike', "%$q%")
                    ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $q).'%')))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'role' => $role,
            'roles' => UserRole::cases(),
            'adminRoles' => Role::orderByDesc('is_system')->orderBy('name')->get(['id', 'name', 'permissions']),
        ]);
    }

    /** Suspendre / réactiver un compte. Un admin ne peut pas se suspendre lui-même. */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas suspendre votre propre compte.');
        abort_if($user->isAdmin() && ! $request->user()->isSuperAdmin(), 403, 'Seul un super-administrateur peut suspendre un administrateur.');

        $user->is_active = ! $user->is_active;
        $user->save();

        if (! $user->is_active) {
            // Déconnexion immédiate partout : jetons API et sessions web.
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        ActivityLog::record($user->is_active ? 'user.reactivated' : 'user.suspended', $user);

        return back()->with('success', $user->is_active ? 'Compte débloqué.' : 'Compte bloqué : la personne est déconnectée partout et ne peut plus se connecter.');
    }

    /**
     * Attribue un rôle d'administration (ou le retire : admin_role_id vide → redevient client).
     * Seuls les comptes clients peuvent devenir admin (un compte pro reste lié à sa fiche) ;
     * on ne peut ni modifier ses propres droits, ni retirer le dernier super-administrateur.
     */
    public function role(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas modifier votre propre rôle.');
        abort_if($user->isProvider(), 422, 'Un compte prestataire ne peut pas devenir administrateur.');

        $roleId = $request->validate(['admin_role_id' => ['nullable', 'exists:roles,id']])['admin_role_id'] ?? null;
        $role = $roleId ? Role::find($roleId) : null;

        // Seul un super-admin peut nommer un super-admin.
        abort_if($role?->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403);
        if ($user->isSuperAdmin() && ! $role?->isSuperAdmin()) {
            abort_unless($request->user()->isSuperAdmin(), 403);
            $others = User::where('role', UserRole::Admin)->where('id', '!=', $user->id)
                ->whereHas('adminRole', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))->count();
            abort_if($others === 0, 422, 'Il doit rester au moins un super-administrateur.');
        }

        $user->role = $role ? UserRole::Admin : UserRole::Client;
        $user->admin_role_id = $role?->id;
        $user->save();
        ActivityLog::record($role ? 'user.admin_role' : 'user.demoted_admin', $user, $role ? ['rôle' => $role->name] : []);

        return back()->with('success', $role ? "{$user->name} est maintenant « {$role->name} »." : $user->name.' n\'est plus administrateur.');
    }

    /** Marque l'email comme vérifié (ex. client qui ne reçoit pas les emails). */
    public function verifyEmail(User $user): RedirectResponse
    {
        $user->markEmailAsVerified();
        ActivityLog::record('user.email_verified_by_admin', $user);

        return back()->with('success', 'Email marqué comme vérifié.');
    }
}
