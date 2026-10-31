<?php

namespace App\Http\Middleware;

use App\Support\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fonctionnalité réservée aux offres qui l'incluent (si les abonnements sont activés dans l'admin).
 * Usage : ->middleware('plan:invoices')
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $provider = $request->user()?->provider;

        if ($provider && ! $provider->hasPlanFeature($feature)) {
            return redirect()->route('pro.subscription')
                ->with('error', '« '.PlanFeatures::label($feature).' » n\'est pas inclus dans votre offre actuelle.');
        }

        return $next($request);
    }
}
