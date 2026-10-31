<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un email vérifié pour les actions sensibles, uniquement si l'option
 * « require_email_verification » est activée dans l'administration. Les admins sont exemptés.
 */
class EnsureEmailIsVerifiedIfRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAdmin() && feature('require_email_verification') && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Confirmez votre adresse email pour continuer.'], 403)
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
