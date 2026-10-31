<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité envoyés avec chaque page :
 * - pas d'affichage du site dans le cadre d'un autre site (hameçonnage, clics piégés) ;
 * - le navigateur ne devine pas le type des fichiers ; adresse d'origine limitée ;
 * - politique de contenu : scripts, styles et images seulement depuis le site (et les tuiles de carte) ;
 * - HTTPS imposé en production.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=()');

        // Avec le serveur Vite de développement (npm run dev), la politique de contenu bloquerait les scripts.
        if (! is_file(public_path('hot'))) {
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                // Alpine.js évalue les expressions des attributs : 'unsafe-eval' est nécessaire.
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https://*.tile.openstreetmap.org",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]));
        }

        if (app()->isProduction() && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
