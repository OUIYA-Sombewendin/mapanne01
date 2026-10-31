<?php

namespace App\Support;

/**
 * Numéros burkinabè : 8 chiffres, indicatif +226.
 * On stocke toujours au format +226XXXXXXXX pour que l'identifiant de connexion
 * soit unique quelle que soit la façon dont l'utilisateur l'a saisi.
 */
class Phone
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($digits, '00226')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '226') && strlen($digits) === 11) {
            $digits = substr($digits, 3);
        }

        return strlen($digits) === 8 ? '+226'.$digits : null;
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }

    /** Affichage lisible : +226 70 12 34 56 */
    public static function format(?string $phone): string
    {
        if (! $phone || ! preg_match('/^\+226(\d{8})$/', $phone, $m)) {
            return (string) $phone;
        }

        return '+226 '.implode(' ', str_split($m[1], 2));
    }

    public static function whatsappUrl(?string $phone, string $message = ''): ?string
    {
        if (! $phone) {
            return null;
        }

        $url = 'https://wa.me/'.ltrim($phone, '+');

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }
}
