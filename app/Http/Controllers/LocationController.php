<?php

namespace App\Http\Controllers;

use App\Support\MapLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Aide à placer un point sur la carte (fiche pro, points de recharge) : recherche d'un lieu par son nom
 * et lecture d'un lien Google Maps ou d'une position WhatsApp. Passe par le serveur car le navigateur
 * n'a le droit d'appeler que MaPanne (politique de sécurité du site).
 */
class LocationController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']])['q']);

        // Coordonnées ou lien collés directement dans la recherche.
        if ($point = MapLink::parse($q)) {
            return response()->json([['label' => 'Position '.$point['lat'].', '.$point['lng']] + $point]);
        }

        $results = Cache::remember('geocode:'.md5(mb_strtolower($q)), 86400, function () use ($q) {
            try {
                $response = Http::timeout(6)
                    ->withHeaders(['User-Agent' => setting('site_name').' (+'.config('app.url').')'])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $q, 'format' => 'jsonv2', 'countrycodes' => 'bf', 'limit' => 6, 'accept-language' => 'fr',
                    ]);
            } catch (Throwable $e) {
                report($e);

                return null;
            }

            return $response->ok() ? collect($response->json())->map(fn ($r) => [
                'label' => $r['display_name'],
                'lat' => round((float) $r['lat'], 7),
                'lng' => round((float) $r['lon'], 7),
            ])->values()->all() : null;
        });

        if ($results === null) {
            Cache::forget('geocode:'.md5(mb_strtolower($q)));

            return response()->json(['message' => 'La recherche de lieux est indisponible pour le moment. Déplacez l\'épingle sur la carte.'], 503);
        }

        return response()->json($results);
    }

    public function link(Request $request): JsonResponse
    {
        $text = (string) $request->validate(['link' => ['required', 'string', 'max:2000']])['link'];
        $point = MapLink::resolve($text);

        return $point
            ? response()->json($point)
            : response()->json(['message' => 'Position introuvable dans ce lien. Dans Google Maps : appuyez longuement sur le lieu, puis « Partager » et copiez le lien.'], 422);
    }
}
