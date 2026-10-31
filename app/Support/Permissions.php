<?php

namespace App\Support;

/**
 * Permissions de l'équipe d'administration. Chaque clé est vérifiée dans le code
 * (middleware `can:` sur les routes, @can dans les vues). Les rôles, eux, se créent dans l'admin.
 */
class Permissions
{
    public static function groups(): array
    {
        return [
            'Pilotage' => [
                'history.view' => 'Consulter l\'historique des actions',
            ],
            'Prestataires' => [
                'providers.view' => 'Voir les prestataires',
                'providers.manage' => 'Valider, refuser, suspendre, modifier, mettre à la une',
            ],
            'Utilisateurs' => [
                'users.view' => 'Voir les utilisateurs',
                'users.manage' => 'Bloquer ou débloquer un compte, confirmer un email',
                'users.impersonate' => 'Accéder au compte d\'un utilisateur (assistance)',
                'roles.manage' => 'Gérer les rôles et nommer les administrateurs',
            ],
            'Modération' => [
                'moderation.manage' => 'Modérer les avis et traiter les signalements',
                'marketplace.manage' => 'Modérer les produits et les demandes de pièces',
            ],
            'Abonnements' => [
                'subscriptions.manage' => 'Gérer les offres et activer les abonnements',
            ],
            'Référentiels et contenus' => [
                'referentials.manage' => 'Types de prestataires, services, catégories, villes',
                'content.manage' => 'Pages de contenu (CGU, FAQ…)',
            ],
            'Configuration' => [
                'settings.manage' => 'Réglages et options',
                'appearance.manage' => 'Apparence, couleurs et page d\'accueil',
                'backups.manage' => 'Sauvegardes et restauration',
                'data.export' => 'Exporter les données',
                'data.import' => 'Importer des données',
            ],
            'Sécurité' => [
                'locks.manage' => 'Verrouiller et déverrouiller des éléments (anti-erreur)',
            ],
        ];
    }

    /** @return array<string, string> */
    /** Icône de chaque groupe (composant x-icon). */
    public static function icons(): array
    {
        return [
            'Pilotage' => 'history', 'Prestataires' => 'providers', 'Utilisateurs' => 'users', 'Modération' => 'flag',
            'Abonnements' => 'card', 'Référentiels et contenus' => 'tag', 'Configuration' => 'settings', 'Sécurité' => 'shield',
        ];
    }

    public static function all(): array
    {
        static $flat;

        return $flat ??= array_merge(...array_values(self::groups()));
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }
}
