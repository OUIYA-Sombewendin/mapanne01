<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lecture d'une position collée par l'utilisateur : lien Google Maps (long ou court), lien OpenStreetMap,
 * position partagée sur WhatsApp, ou simples coordonnées « 12.3714, -1.5197 ».
 */
class MapLink
{
    /** Hôtes des liens courts que l'on accepte de suivre (jamais une adresse quelconque). */
    private const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl', 'g.co'];

    /** @return array{lat: float, lng: float}|null */
    public static function parse(string $text): ?array
    {
        $text = trim(urldecode($text));

        $patterns = [
            '/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/',                // …!3d12.37!4d-1.52 (point exact d'un lieu)
            '/@(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)/',                  // …/@12.37,-1.52,17z
            '/[?&](?:q|query|ll|destination|center)=(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)/', // ?q=12.37,-1.52
            '/[?&]mlat=(-?\d{1,2}\.\d+)&mlon=(-?\d{1,3}\.\d+)/',        // OpenStreetMap
            '/#map=\d+\/(-?\d{1,2}\.\d+)\/(-?\d{1,3}\.\d+)/',           // OpenStreetMap
            '/^(-?\d{1,2}\.\d+)\s*[,;\s]\s*(-?\d{1,3}\.\d+)$/',          // 12.3714, -1.5197
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if (abs($lat) <= 90 && abs($lng) <= 180) {
                    return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
                }
            }
        }

        return null;
    }

    /** Lit le lien ; un lien court Google (maps.app.goo.gl/…) est d'abord déplié côté serveur. */
    public static function resolve(string $text): ?array
    {
        if ($point = self::parse($text)) {
            return $point;
        }

        $url = trim($text);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($host, self::SHORT_HOSTS, true) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        // On suit au plus 3 redirections, sans jamais charger la page finale.
        try {
            for ($i = 0; $i < 3; $i++) {
                $location = Http::timeout(5)->withoutRedirecting()->get($url)->header('Location');
                if (! $location) {
                    return null;
                }
                if ($point = self::parse($location)) {
                    return $point;
                }
                $url = $location;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
