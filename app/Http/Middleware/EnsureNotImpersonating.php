<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Actions réservées à la personne elle-même, interdites à un admin qui accède à son compte (mot de passe…). */
class EnsureNotImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has(ImpersonationController::SESSION_KEY)) {
            return back()->with('error', 'Action impossible pendant l\'accès au compte d\'un utilisateur.');
        }

        return $next($request);
    }
}
