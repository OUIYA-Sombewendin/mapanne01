<?php

namespace App\Support;

/**
 * Entretiens proposés dans le carnet, avec leur fréquence conseillée (en mois) selon le véhicule.
 * Les rappels se basent sur des dates, pas sur le kilométrage, que peu de conducteurs relèvent.
 */
class MaintenanceTasks
{
    /**
     * months : fréquence par type de véhicule (absent = non proposé pour ce type).
     * energy : réservé aux véhicules thermiques ou électriques (null = tous).
     * suggested : coché d'office à la création des rappels.
     *
     * @return array<string, array{label:string, icon:string, months:array<string,int>, energy:?string, suggested:bool}>
     */
    public static function all(): array
    {
        $all = fn (int $m) => ['moto' => $m, 'voiture' => $m, 'tricycle' => $m, 'camion' => $m];

        return [
            'vidange' => ['label' => 'Vidange', 'icon' => '🛢️', 'months' => ['moto' => 2, 'tricycle' => 2, 'voiture' => 6, 'camion' => 3], 'energy' => 'thermique', 'suggested' => true],
            'filtre_air' => ['label' => 'Filtre à air (poussière)', 'icon' => '🌬️', 'months' => ['moto' => 4, 'tricycle' => 4, 'voiture' => 6, 'camion' => 6], 'energy' => 'thermique', 'suggested' => false],
            'chaine' => ['label' => 'Graissage de la chaîne', 'icon' => '⛓️', 'months' => ['moto' => 1, 'tricycle' => 1], 'energy' => null, 'suggested' => false],
            'pneus' => ['label' => 'Pression et usure des pneus', 'icon' => '🛞', 'months' => $all(2), 'energy' => null, 'suggested' => false],
            'freins' => ['label' => 'Contrôle des freins', 'icon' => '🛑', 'months' => $all(6), 'energy' => null, 'suggested' => true],
            'batterie' => ['label' => 'Batterie (bornes, niveau)', 'icon' => '🔋', 'months' => ['voiture' => 6, 'tricycle' => 6, 'camion' => 6], 'energy' => 'thermique', 'suggested' => false],
            'batterie_ev' => ['label' => 'Batterie électrique et câbles', 'icon' => '⚡', 'months' => $all(3), 'energy' => 'electrique', 'suggested' => true],
            'chargeur' => ['label' => 'Chargeur et prise de recharge', 'icon' => '🔌', 'months' => $all(6), 'energy' => 'electrique', 'suggested' => false],
            'assurance' => ['label' => 'Assurance', 'icon' => '📄', 'months' => $all(12), 'energy' => null, 'suggested' => true],
            'visite_technique' => ['label' => 'Visite technique', 'icon' => '🔍', 'months' => ['voiture' => 12, 'tricycle' => 12, 'camion' => 6], 'energy' => null, 'suggested' => true],
        ];
    }

    /** Entretiens qui concernent ce véhicule, avec la fréquence propre à son type. */
    public static function for(string $type, string $energy): array
    {
        return collect(self::all())
            ->filter(fn ($t) => isset($t['months'][$type]) && ($t['energy'] === null || $t['energy'] === $energy))
            ->map(fn ($t) => $t + ['every' => $t['months'][$type]])
            ->all();
    }

    public static function label(?string $task): ?string
    {
        return $task ? (self::all()[$task]['label'] ?? null) : null;
    }

    public static function icon(?string $task): string
    {
        return self::all()[$task]['icon'] ?? '🔧';
    }

    public static function energies(): array
    {
        return AssistProblems::energies();
    }
}
