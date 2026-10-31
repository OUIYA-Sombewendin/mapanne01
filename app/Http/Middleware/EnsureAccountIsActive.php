<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte suspendu par l'administration est déconnecté à sa prochaine requête.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            if ($request->expectsJson()) {
                $user->currentAccessToken()?->delete();
                abort(403, 'Compte suspendu.');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Votre compte a été suspendu. Contactez le support MaPanne.']);
        }

        return $next($request);
    }
}
