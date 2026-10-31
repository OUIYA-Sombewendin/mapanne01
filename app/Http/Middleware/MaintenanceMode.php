<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode maintenance activable depuis l'administration : le site affiche un message,
 * mais la connexion et l'espace admin restent accessibles pour le désactiver.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! feature('maintenance_enabled') || $request->user()?->isAdmin()
            || $request->routeIs('login', 'logout', 'admin.*')) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return response()->json(['message' => setting('maintenance_message')], 503);
        }

        return response()->view('pages.maintenance', [], 503);
    }
}
