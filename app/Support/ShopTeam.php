<?php

namespace App\Support;

/**
 * Rôles dans une boutique et zones de l'espace pro auxquelles chacun a accès.
 * Le propriétaire a toujours tout ; les employés sont ajoutés par lui dans « Équipe ».
 */
class ShopTeam
{
    public const ROLES = [
        'manager' => ['Gérant', 'Tout gérer sauf l\'équipe, l\'abonnement et l\'ajout de boutiques'],
        'seller' => ['Vendeur', 'Catalogue, stock, ventes et demandes de pièces'],
        'cashier' => ['Caissier', 'Ventes et factures uniquement'],
    ];

    /** zone => libellé (utilisé dans l'écran Équipe pour expliquer les rôles) */
    public const AREAS = [
        'dashboard' => 'Tableau de bord',
        'availability' => 'Disponibilité',
        'assist' => 'Dépannage en direct',
        'profile' => 'Fiche, horaires, photos',
        'catalog' => 'Catalogue',
        'stock' => 'Stock',
        'invoices' => 'Ventes et factures',
        'expenses' => 'Dépenses',
        'reports' => 'Rapports et vue d\'ensemble',
        'stats' => 'Statistiques de la fiche',
        'requests' => 'Demandes de pièces',
        'subscription' => 'Abonnement',
        'team' => 'Équipe',
        'shops' => 'Boutiques',
    ];

    private const ACCESS = [
        'manager' => ['dashboard', 'availability', 'assist', 'stats', 'profile', 'catalog', 'stock', 'invoices', 'expenses', 'reports', 'requests'],
        'seller' => ['dashboard', 'availability', 'catalog', 'stock', 'invoices', 'requests'],
        'cashier' => ['dashboard', 'invoices'],
    ];

    public static function allows(?string $role, string $area): bool
    {
        if ($role === 'owner') {
            return true;
        }

        return $role !== null && in_array($area, self::ACCESS[$role] ?? [], true);
    }

    public static function label(?string $role): string
    {
        return $role === 'owner' ? 'Propriétaire' : (self::ROLES[$role][0] ?? '—');
    }
}
