<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte un administrateur inactif depuis trop longtemps (Réglages › Sécurité, 30 min par défaut) :
 * protège un ordinateur partagé laissé ouvert. 0 = désactivé.
 */
class AdminIdleTimeout
{
    public const KEY = 'admin_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $minutes = (int) setting('admin_idle_minutes');
        $last = $request->session()->get(self::KEY);

        if ($minutes > 0 && $last && now()->timestamp - $last > $minutes * 60 && ! $request->session()->has(ImpersonationController::SESSION_KEY)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => "Vous avez été déconnecté après {$minutes} minutes d'inactivité."]);
        }

        $request->session()->put(self::KEY, now()->timestamp);

        return $next($request);
    }
}
