<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Accès au compte d'un utilisateur (assistance) : l'admin voit exactement ce que voit la personne.
 * - permission users.impersonate ;
 * - jamais sur un autre administrateur ni sur un compte bloqué ;
 * - bandeau permanent « Revenir à mon compte » ; changement de mot de passe interdit pendant l'accès ;
 * - début et fin notés dans l'historique.
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();
        abort_if($user->id === $admin->id, 422);
        abort_if($user->isAdmin(), 403, 'Impossible d\'accéder au compte d\'un administrateur.');
        abort_unless($user->is_active, 422, 'Ce compte est bloqué : débloquez-le avant d\'y accéder.');

        ActivityLog::record('user.impersonated', $user);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $admin->id);

        return redirect($user->homeRoute())->with('success', "Vous êtes dans le compte de {$user->name}.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);
        $admin = $adminId ? User::find($adminId) : null;
        abort_unless($admin && $admin->isAdmin() && $admin->is_active, 403);

        $user = $request->user();
        Auth::login($admin);
        $request->session()->regenerate();
        ActivityLog::record('user.impersonation_ended', $user);

        return redirect()->route('admin.users.index', ['q' => $user->email])->with('success', 'Vous êtes revenu dans votre compte administrateur.');
    }
}
