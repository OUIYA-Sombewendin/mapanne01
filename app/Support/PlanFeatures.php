<?php

namespace App\Support;

/**
 * Fonctionnalités et limites qu'une offre d'abonnement peut inclure.
 * Les offres (prix, durée, contenu) se composent dans l'admin à partir de cette liste.
 */
class PlanFeatures
{
    /** @return array<string, array{label:string, sellers_only?:bool}> */
    public static function features(): array
    {
        return [
            'catalog' => ['label' => 'Catalogue de pièces sur le marketplace', 'sellers_only' => true],
            'catalog_import' => ['label' => 'Import du catalogue (Excel / CSV)', 'sellers_only' => true],
            'part_requests' => ['label' => 'Répondre aux demandes de pièces des clients', 'sellers_only' => true],
            'stock' => ['label' => 'Gestion du stock : entrées, sorties, inventaire, alertes', 'sellers_only' => true],
            'invoices' => ['label' => 'Factures, devis et suivi des paiements'],
            'expenses' => ['label' => 'Suivi des dépenses'],
            'reports' => ['label' => 'Rapports : chiffre d\'affaires, marge, bénéfice, export'],
            'statistics' => ['label' => 'Statistiques de vues et de contacts'],
            'featured' => ['label' => 'Mise « à la une » dans la recherche et le marketplace'],
        ];
    }

    /** @return array<string, array{label:string, help:string}> 0 = illimité */
    public static function limits(): array
    {
        return [
            'max_products' => ['label' => 'Produits max. au catalogue', 'help' => '0 = illimité'],
            'max_shops' => ['label' => 'Boutiques max. (commerçant multi-boutiques)', 'help' => '0 = selon le réglage général'],
            'max_team_members' => ['label' => 'Employés max. par boutique', 'help' => '0 = selon le réglage général'],
        ];
    }

    public static function label(string $feature): string
    {
        return self::features()[$feature]['label'] ?? $feature;
    }
}
