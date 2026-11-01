<?php

namespace App\Support;

/**
 * Problèmes proposés au conducteur en panne, et métiers (fiches métier) prévenus pour chacun.
 */
class AssistProblems
{
    /**
     * electric : proposé seulement pour un véhicule électrique ; fuel : seulement pour un véhicule thermique.
     *
     * @return array<string, array{label:string, icon:string, kinds:list<string>, electric?:bool, fuel?:bool}>
     */
    public static function all(): array
    {
        $repair = ['mechanic', 'garage', 'tow'];

        return [
            'crevaison' => ['label' => 'Crevaison', 'icon' => '🛞', 'kinds' => $repair],
            'batterie' => ['label' => 'Batterie à plat', 'icon' => '🔋', 'kinds' => $repair],
            'demarrage' => ['label' => 'Ne démarre pas', 'icon' => '🔑', 'kinds' => $repair],
            'surchauffe' => ['label' => 'Surchauffe, fumée', 'icon' => '🌡️', 'kinds' => $repair],
            'panne_seche' => ['label' => 'Panne d\'essence', 'icon' => '⛽', 'kinds' => $repair, 'fuel' => true],
            'electrique' => ['label' => 'Batterie électrique vide', 'icon' => '⚡', 'kinds' => $repair, 'electric' => true],
            'transport_moto' => ['label' => 'Transporter ma moto', 'icon' => '🛵', 'kinds' => ['tow']],
            'remorquage' => ['label' => 'Remorquage', 'icon' => '🚛', 'kinds' => ['tow']],
            'accident' => ['label' => 'Accident (sans blessé)', 'icon' => '💥', 'kinds' => ['tow', 'garage']],
            'autre' => ['label' => 'Autre problème', 'icon' => '🔧', 'kinds' => $repair],
        ];
    }

    public static function label(string $problem): string
    {
        return self::all()[$problem]['label'] ?? $problem;
    }

    public static function icon(string $problem): string
    {
        return self::all()[$problem]['icon'] ?? '🔧';
    }

    /** Fiches métier concernées par ce problème. */
    public static function kinds(string $problem): array
    {
        return self::all()[$problem]['kinds'] ?? [];
    }

    public static function energies(): array
    {
        return ['thermique' => 'Essence ou diesel', 'electrique' => 'Électrique'];
    }
}
