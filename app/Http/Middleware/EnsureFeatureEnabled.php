<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route disponible seulement si l'option correspondante est activée dans l'admin.
 * Usage : ->middleware('feature:marketplace_enabled')
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(feature($feature), 404);

        return $next($request);
    }
}
