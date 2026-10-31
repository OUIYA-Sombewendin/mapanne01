<?php

namespace App\Http\Middleware;

use App\Support\ShopTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espace pro : vérifie que le compte a une boutique active et le droit d'accéder à la zone demandée
 * (propriétaire : tout ; employé : selon son rôle). Usage : ->middleware('shop:stock')
 */
class EnsureShopAccess
{
    public function handle(Request $request, Closure $next, string $area = 'dashboard'): Response
    {
        $user = $request->user();

        if (! $user->provider) {
            return redirect()->route('account.show')->with('error', 'Vous n\'êtes rattaché à aucune boutique pour le moment.');
        }

        if (! $user->canShop($area)) {
            abort(403, '« '.(ShopTeam::AREAS[$area] ?? $area).' » n\'est pas accessible avec votre rôle ('.ShopTeam::label($user->shopRole($user->provider)).').');
        }

        return $next($request);
    }
}
