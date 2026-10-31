<?php

namespace App\Support;

/**
 * Couleurs du site choisies dans Admin › Apparence. À partir d'une couleur principale et d'une
 * couleur sombre, on calcule les nuances 50 → 900 utilisées par Tailwind (variables CSS --color-*).
 */
class Theme
{
    public const DEFAULT_BRAND = '#f45d0b';

    public const DEFAULT_INK = '#18223d';

    public const DEFAULT_ACCENT = '#1d4f91';

    /** Proportion de blanc (valeur positive) ou de noir (négative) mélangée à la couleur de base. */
    private const BRAND_STEPS = [50 => .92, 100 => .84, 200 => .68, 300 => .48, 400 => .24, 500 => 0, 600 => -.13, 700 => -.28, 800 => -.42, 900 => -.55];

    private const INK_STEPS = [50 => .955, 100 => .89, 200 => .77, 300 => .58, 400 => .4, 500 => .26, 600 => .15, 700 => .07, 800 => 0, 900 => -.4];

    /** Feuille de style à injecter ; vide si les couleurs par défaut sont conservées. */
    public static function css(): string
    {
        $brand = self::normalize(setting('theme_brand_color')) ?? self::DEFAULT_BRAND;
        $ink = self::normalize(setting('theme_ink_color')) ?? self::DEFAULT_INK;

        $accent = self::normalize(setting('theme_accent_color')) ?? self::DEFAULT_ACCENT;

        $vars = [];
        if ($accent !== self::DEFAULT_ACCENT) {
            foreach (self::palette($accent, self::BRAND_STEPS) as $step => $hex) {
                $vars[] = "--color-accent-{$step}:{$hex}";
            }
        }
        if ($brand !== self::DEFAULT_BRAND) {
            foreach (self::palette($brand, self::BRAND_STEPS) as $step => $hex) {
                $vars[] = "--color-brand-{$step}:{$hex}";
            }
        }
        if ($ink !== self::DEFAULT_INK) {
            $palette = self::palette($ink, self::INK_STEPS);
            foreach ($palette as $step => $hex) {
                $vars[] = "--color-ink-{$step}:{$hex}";
            }
        }

        return $vars ? ':root{'.implode(';', $vars).'}' : '';
    }

    public static function brand(): string
    {
        return self::normalize(setting('theme_brand_color')) ?? self::DEFAULT_BRAND;
    }

    /** @return array<int, string> */
    public static function palette(string $base, array $steps = self::BRAND_STEPS): array
    {
        [$r, $g, $b] = self::rgb($base);

        return array_map(function (float $mix) use ($r, $g, $b) {
            $target = $mix >= 0 ? 255 : 0;
            $amount = abs($mix);

            return sprintf('#%02x%02x%02x', ...array_map(fn ($c) => (int) round($c + ($target - $c) * $amount), [$r, $g, $b]));
        }, $steps);
    }

    public static function normalize(mixed $hex): ?string
    {
        $hex = strtolower(trim((string) $hex));

        return preg_match('/^#[0-9a-f]{6}$/', $hex) ? $hex : null;
    }

    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }
}
