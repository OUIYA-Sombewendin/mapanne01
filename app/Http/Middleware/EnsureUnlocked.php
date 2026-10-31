<?php

namespace App\Http\Middleware;

use App\Models\Concerns\Lockable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse toute action d'administration sur un élément verrouillé (paramètre de route verrouillable).
 * Usage : ->middleware('unlocked')
 */
class EnsureUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->route()?->parameters() ?? [] as $model) {
            if (is_object($model) && in_array(Lockable::class, class_uses_recursive($model), true) && $model->isLocked()) {
                $message = 'Élément verrouillé : déverrouillez-le d\'abord pour le modifier ou le supprimer.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 423)
                    : back()->with('error', $message)->setStatusCode(303);
            }
        }

        return $next($request);
    }
}
