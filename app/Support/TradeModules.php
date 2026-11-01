<?php

namespace App\Support;

/**
 * Modules activables pour chaque métier (Référentiels › espace du métier).
 * Un module retiré disparaît de l'espace pro de tous les prestataires du métier.
 * La fiche, les horaires, les photos et les avis sont toujours disponibles.
 * Les clés assist…shops sont aussi des zones de l'espace pro (voir App\Support\ShopTeam).
 */
class TradeModules
{
    /** @return array<string, array{label:string, help:string, icon:string, requires?:list<string>}> */
    public static function all(): array
    {
        return [
            'services' => ['label' => 'Services proposés', 'help' => 'Le prestataire coche ses services ; il apparaît quand un client les cherche.', 'icon' => 'wrench'],
            'assist' => ['label' => 'Dépannage en direct', 'help' => 'Recevoir les demandes des conducteurs en panne à proximité et y répondre.', 'icon' => 'phone'],
            'catalog' => ['label' => 'Catalogue de pièces', 'help' => 'Vente de pièces sur le marketplace.', 'icon' => 'cube'],
            'requests' => ['label' => 'Demandes de pièces', 'help' => 'Répondre aux pièces recherchées par les clients.', 'icon' => 'flag', 'requires' => ['catalog']],
            'stock' => ['label' => 'Stock et inventaire', 'help' => 'Entrées, sorties, alertes de réapprovisionnement.', 'icon' => 'layers', 'requires' => ['catalog']],
            'invoices' => ['label' => 'Ventes, factures et devis', 'help' => 'Factures et devis imprimables, suivi des paiements.', 'icon' => 'card'],
            'expenses' => ['label' => 'Dépenses', 'help' => 'Loyer, salaires, achats : pour connaître le bénéfice.', 'icon' => 'transfer'],
            'reports' => ['label' => 'Vue d\'ensemble et rapports', 'help' => 'Chiffre d\'affaires, marge, bénéfice, exports.', 'icon' => 'chart'],
            'team' => ['label' => 'Équipe', 'help' => 'Ajouter des employés (gérant, vendeur, caissier).', 'icon' => 'users'],
            'shops' => ['label' => 'Plusieurs établissements', 'help' => 'Gérer plusieurs boutiques ou sites avec un seul compte.', 'icon' => 'shop'],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $module): string
    {
        return self::all()[$module]['label'] ?? $module;
    }

    /** Modules proposés par défaut selon la fiche métier. */
    public static function defaults(?string $kind): array
    {
        return match ($kind) {
            'shop' => ['services', 'catalog', 'requests', 'stock', 'invoices', 'expenses', 'reports', 'team', 'shops'],
            'garage' => ['services', 'assist', 'invoices', 'expenses', 'reports', 'team'],
            'station' => ['services', 'invoices', 'expenses', 'reports', 'team', 'shops'],
            'mechanic', 'tow' => ['services', 'assist', 'invoices', 'expenses', 'reports'],
            default => ['services'],
        };
    }

    /** Garde les modules connus et retire ceux dont un prérequis manque. */
    public static function normalize(array $modules): array
    {
        $modules = array_values(array_intersect(self::keys(), $modules));

        return array_values(array_filter($modules, fn ($m) => ! array_diff(self::all()[$m]['requires'] ?? [], $modules)));
    }
}
