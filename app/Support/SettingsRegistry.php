<?php

namespace App\Support;

/**
 * Catalogue de tous les réglages modifiables depuis l'administration.
 *
 * Chaque entrée : type, libellé, valeur par défaut, aide éventuelle, options (select).
 * Types : bool, int, float, string, text, email, secret (chiffré), select, int_list, list, pairs, color, image.
 *  - list  : une valeur par ligne
 *  - pairs : « clé | libellé » par ligne (ex. motifs de signalement, numéros d'urgence)
 *
 * Ajouter une option = ajouter une entrée ici ET l'utiliser dans le code via setting()/feature().
 */
class SettingsRegistry
{
    public static function groups(): array
    {
        return [
            'general' => [
                'label' => 'Général',
                'icon' => '⚙️',
                'settings' => [
                    'site_name' => ['type' => 'string', 'label' => 'Nom du site', 'default' => 'MaPanne'],
                    'site_tagline' => ['type' => 'string', 'label' => 'Slogan', 'default' => 'Dépannage auto & moto au Burkina Faso'],
                    'support_email' => ['type' => 'email', 'label' => 'Email du support', 'default' => 'support@mapanne.bf'],
                    'support_phone' => ['type' => 'string', 'label' => 'Téléphone du support', 'default' => '+22670000000'],
                    'announcement_enabled' => ['type' => 'bool', 'label' => 'Afficher une bannière d\'annonce', 'default' => false],
                    'announcement_text' => ['type' => 'string', 'label' => 'Texte de la bannière', 'default' => ''],
                    'maintenance_enabled' => ['type' => 'bool', 'label' => 'Mode maintenance (site fermé au public, admins exceptés)', 'default' => false],
                    'maintenance_message' => ['type' => 'text', 'label' => 'Message de maintenance', 'default' => 'MaPanne est en maintenance. Revenez dans quelques minutes.'],
                ],
            ],
            'accounts' => [
                'label' => 'Comptes et inscriptions',
                'icon' => '👤',
                'settings' => [
                    'registration_clients_enabled' => ['type' => 'bool', 'label' => 'Inscriptions clients ouvertes', 'default' => true],
                    'registration_providers_enabled' => ['type' => 'bool', 'label' => 'Inscriptions professionnelles ouvertes', 'default' => true],
                    'login_with_phone_enabled' => ['type' => 'bool', 'label' => 'Autoriser la connexion par numéro de téléphone (en plus de l\'email)', 'default' => true],
                    'client_phone_required' => ['type' => 'bool', 'label' => 'Téléphone obligatoire pour les clients', 'default' => false],
                    'require_email_verification' => ['type' => 'bool', 'label' => 'Email vérifié obligatoire pour agir (avis, demandes, espace pro)', 'default' => false, 'help' => 'Activez-le une fois l\'envoi d\'emails configuré.'],
                ],
            ],
            'search' => [
                'label' => 'Recherche et carte',
                'icon' => '🔎',
                'settings' => [
                    'search_radii' => ['type' => 'int_list', 'label' => 'Rayons proposés (km)', 'default' => [2, 5, 10, 20, 50]],
                    'default_radius' => ['type' => 'int', 'label' => 'Rayon par défaut (km)', 'default' => 10],
                    'per_page' => ['type' => 'int', 'label' => 'Résultats par page', 'default' => 20],
                    'map_default_lat' => ['type' => 'float', 'label' => 'Centre de carte par défaut — latitude', 'default' => 12.3714],
                    'map_default_lng' => ['type' => 'float', 'label' => 'Centre de carte par défaut — longitude', 'default' => -1.5197],
                    'featured_enabled' => ['type' => 'bool', 'label' => 'Mettre en avant les prestataires et produits « à la une »', 'default' => false, 'help' => 'Prépare les offres de visibilité payantes.'],
                ],
            ],
            'providers' => [
                'label' => 'Prestataires',
                'icon' => '🔧',
                'settings' => [
                    'max_photos' => ['type' => 'int', 'label' => 'Photos max. par fiche', 'default' => 6],
                    'max_image_kb' => ['type' => 'int', 'label' => 'Taille max. d\'une image (Ko)', 'default' => 2048],
                    'lock_approved_fields' => ['type' => 'bool', 'label' => 'Verrouiller le nom, le téléphone, la position et le RCCM d’une fiche validée (le prestataire fait une demande de modification)', 'default' => true],
                    'sensitive_changes_review' => ['type' => 'bool', 'label' => 'Signaler à l\'admin les changements sensibles d\'une fiche validée (nom, téléphone, position, type)', 'default' => true],
                    'sensitive_changes_unpublish' => ['type' => 'bool', 'label' => '… et dépublier la fiche jusqu\'à nouvelle validation', 'default' => false],
                ],
            ],
            'reviews' => [
                'label' => 'Avis et signalements',
                'icon' => '★',
                'settings' => [
                    'reviews_enabled' => ['type' => 'bool', 'label' => 'Avis clients activés', 'default' => true],
                    'reviews_premoderation' => ['type' => 'bool', 'label' => 'Valider chaque avis avant publication', 'default' => false],
                    'reviews_per_hour' => ['type' => 'int', 'label' => 'Avis max. par compte et par heure', 'default' => 5],
                    'report_reasons' => ['type' => 'pairs', 'label' => 'Motifs de signalement (clé | libellé)', 'default' => [
                        'fausses_infos' => 'Informations fausses ou trompeuses',
                        'injoignable' => 'Numéro injoignable ou faux',
                        'ferme' => 'Établissement fermé / n\'existe plus',
                        'arnaque' => 'Arnaque ou tarif abusif',
                        'contrefacon' => 'Pièce contrefaite ou non conforme',
                        'comportement' => 'Comportement inapproprié',
                        'faux_avis' => 'Avis suspect ou faux',
                        'autre' => 'Autre problème',
                    ]],
                ],
            ],
            'marketplace' => [
                'label' => 'Marketplace',
                'icon' => '⚙️',
                'settings' => [
                    'marketplace_enabled' => ['type' => 'bool', 'label' => 'Marketplace de pièces activé', 'default' => true],
                    'price_watch_public' => ['type' => 'bool', 'label' => 'Afficher le « prix constaté » (fourchette chez les autres boutiques) sur la page d\'une pièce', 'default' => true],
                    'price_watch_min_shops' => ['type' => 'int', 'label' => 'Nombre minimum de boutiques pour afficher un prix constaté', 'default' => 3],
                    'products_premoderation' => ['type' => 'bool', 'label' => 'Valider chaque nouveau produit avant publication', 'default' => false],
                    'product_max_photos' => ['type' => 'int', 'label' => 'Photos max. par produit', 'default' => 4],
                    'allow_used_parts' => ['type' => 'bool', 'label' => 'Autoriser les pièces d\'occasion', 'default' => true],
                    'show_stock_quantity' => ['type' => 'bool', 'label' => 'Afficher la quantité exacte en stock aux clients', 'default' => false],
                    'delivery_enabled' => ['type' => 'bool', 'label' => 'Livraison (les boutiques peuvent proposer de livrer)', 'default' => false, 'help' => 'Désactivée : MaPanne met seulement en relation le client et la boutique.'],
                    'payment_methods' => ['type' => 'list', 'label' => 'Moyens de paiement proposés aux boutiques', 'default' => ['Espèces', 'Orange Money', 'Moov Money', 'Coris Money', 'Virement / chèque']],
                    'part_requests_enabled' => ['type' => 'bool', 'label' => 'Demandes de pièces (le client décrit sa pièce, les boutiques font une offre)', 'default' => true],
                    'part_request_days' => ['type' => 'int', 'label' => 'Durée de validité d\'une demande (jours)', 'default' => 15],
                    'part_requests_per_day' => ['type' => 'int', 'label' => 'Demandes max. par client et par jour', 'default' => 5],
                    'catalog_import_enabled' => ['type' => 'bool', 'label' => 'Import du catalogue par fichier CSV / Excel', 'default' => true],
                ],
            ],
            'notifications' => [
                'label' => 'Notifications',
                'icon' => '✉️',
                'settings' => [
                    'push_enabled' => ['type' => 'bool', 'label' => 'Notifications push (navigateur et téléphone, gratuites)', 'default' => true, 'help' => 'Nécessite les clés VAPID dans .env (php artisan webpush:vapid) et un site en HTTPS en production.'],
                    'sms_enabled' => ['type' => 'bool', 'label' => 'Envoyer aussi des SMS (payant)', 'default' => false],
                    'sms_driver' => ['type' => 'select', 'label' => 'Fournisseur SMS', 'default' => 'orange', 'options' => ['orange' => 'Orange SMS API', 'log' => 'Journal uniquement (test)']],
                    'sms_client_id' => ['type' => 'string', 'label' => 'SMS : identifiant client (Client ID)', 'default' => ''],
                    'sms_client_secret' => ['type' => 'secret', 'label' => 'SMS : clé secrète (Client secret)', 'default' => ''],
                    'sms_sender' => ['type' => 'string', 'label' => 'SMS : numéro expéditeur', 'default' => '', 'help' => 'Numéro fourni par l\'opérateur avec le contrat SMS.'],
                    'whatsapp_enabled' => ['type' => 'bool', 'label' => 'Envoyer aussi des messages WhatsApp (payant)', 'default' => false, 'help' => 'API WhatsApp Business de Meta. Les messages hors conversation en cours exigent des modèles validés par Meta.'],
                    'whatsapp_phone_id' => ['type' => 'string', 'label' => 'WhatsApp : identifiant du numéro (Phone number ID)', 'default' => ''],
                    'whatsapp_token' => ['type' => 'secret', 'label' => 'WhatsApp : jeton d\'accès', 'default' => ''],
                    'admin_notification_email' => ['type' => 'email', 'label' => 'Email qui reçoit les alertes admin', 'default' => ''],
                    'notify_admin_new_provider' => ['type' => 'bool', 'label' => 'Alerter l\'admin à chaque nouvelle inscription pro', 'default' => true],
                    'notify_admin_new_report' => ['type' => 'bool', 'label' => 'Alerter l\'admin à chaque signalement', 'default' => true],
                    'notify_provider_decision' => ['type' => 'bool', 'label' => 'Informer le prestataire de la validation / du refus / de la suspension', 'default' => true],
                    'notify_provider_new_review' => ['type' => 'bool', 'label' => 'Informer le prestataire d\'un nouvel avis', 'default' => true],
                    'notify_client_new_offer' => ['type' => 'bool', 'label' => 'Informer le client d\'une offre sur sa demande de pièce', 'default' => true],
                    'notify_seller_product_hidden' => ['type' => 'bool', 'label' => 'Informer le vendeur quand un produit est masqué', 'default' => true],
                ],
            ],
            'mail' => [
                'label' => 'Serveur d\'envoi d\'emails',
                'icon' => '📮',
                'settings' => [
                    'mail_mailer' => ['type' => 'select', 'label' => 'Mode d\'envoi', 'default' => 'env', 'options' => ['env' => 'Configuration du serveur (.env)', 'smtp' => 'SMTP (ci-dessous)', 'log' => 'Journal uniquement (test)']],
                    'mail_host' => ['type' => 'string', 'label' => 'Serveur SMTP', 'default' => ''],
                    'mail_port' => ['type' => 'int', 'label' => 'Port', 'default' => 587],
                    'mail_encryption' => ['type' => 'select', 'label' => 'Chiffrement', 'default' => 'tls', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Aucun']],
                    'mail_username' => ['type' => 'string', 'label' => 'Identifiant SMTP', 'default' => ''],
                    'mail_password' => ['type' => 'secret', 'label' => 'Mot de passe SMTP', 'default' => ''],
                    'mail_from_address' => ['type' => 'email', 'label' => 'Adresse d\'expédition', 'default' => ''],
                    'mail_from_name' => ['type' => 'string', 'label' => 'Nom d\'expéditeur', 'default' => 'MaPanne'],
                ],
            ],
            'appearance' => [
                'label' => 'Apparence et couleurs',
                'icon' => '🎨',
                'settings' => [
                    'theme_brand_color' => ['type' => 'color', 'label' => 'Couleur principale (boutons, liens, accents)', 'default' => '#f45d0b', 'presets' => ['#f45d0b', '#e11d48', '#16a34a', '#0284c7', '#7c3aed', '#ca8a04']],
                    'theme_ink_color' => ['type' => 'color', 'label' => 'Couleur sombre (textes, menus, fonds sombres)', 'default' => '#18223d', 'presets' => ['#18223d', '#1f2937', '#0f3d3e', '#3b1d4a', '#1e293b']],
                    'theme_accent_color' => ['type' => 'color', 'label' => 'Couleur d\'accent (titres, en-têtes, menu actif de l\'admin et de l\'espace pro)', 'default' => '#1d4f91', 'presets' => ['#1d4f91', '#0e7490', '#047857', '#6d28d9', '#be123c', '#18223d']],
                    'theme_header_style' => ['type' => 'select', 'label' => 'Fond de l\'en-tête du site', 'default' => 'dark', 'options' => ['light' => 'Clair (blanc)', 'dark' => 'Sombre (couleur sombre du thème)', 'brand' => 'Couleur principale']],
                    'theme_footer_style' => ['type' => 'select', 'label' => 'Fond du pied de page', 'default' => 'dark', 'options' => ['light' => 'Clair (blanc)', 'dark' => 'Sombre (couleur sombre du thème)', 'brand' => 'Couleur principale']],
                    'site_logo' => ['type' => 'image', 'label' => 'Logo (carré, PNG, JPG ou WebP)', 'default' => '', 'help' => 'Laisser vide pour garder le logo par défaut.'],
                ],
            ],
            'home' => [
                'label' => 'Page d\'accueil',
                'icon' => '🏠',
                'settings' => [
                    'home_title' => ['type' => 'string', 'label' => 'Titre principal', 'default' => 'En panne ?'],
                    'home_highlight' => ['type' => 'string', 'label' => 'Titre en couleur', 'default' => 'Trouvez de l\'aide près de vous.'],
                    'home_subtitle' => ['type' => 'text', 'label' => 'Sous-titre', 'default' => 'Mécaniciens, garages, dépanneurs, stations-service et vendeurs de pièces contrôlés par l\'équipe MaPanne.'],
                    'home_show_featured' => ['type' => 'bool', 'label' => 'Afficher les prestataires à la une et les mieux notés', 'default' => true],
                    'home_show_plans' => ['type' => 'bool', 'label' => 'Afficher les offres pour les professionnels (si les abonnements sont activés)', 'default' => true],
                    'home_problems' => ['type' => 'pairs', 'label' => 'Raccourcis « Problèmes fréquents » (code du service | libellé)', 'default' => [
                        'crevaison-vulcanisation' => '🛞 Crevaison',
                        'batterie-recharge-remplacement' => '🔋 Batterie à plat',
                        'mecanique-moto' => '🏍️ Ma moto ne démarre pas',
                        'remorquage' => '🚛 Remorquage',
                        'electricite-automobile' => '⚡ Problème électrique',
                        'freinage' => '🛑 Freins',
                        'vidange' => '🛢️ Vidange',
                    ]],
                ],
            ],
            'subscriptions' => [
                'label' => 'Abonnements',
                'icon' => '💳',
                'settings' => [
                    'subscriptions_enabled' => ['type' => 'bool', 'label' => 'Abonnements activés (les fonctionnalités dépendent de l\'offre)', 'default' => false, 'help' => 'Désactivés : toutes les fonctionnalités sont gratuites pour tous les prestataires.'],
                    'payment_orange_number' => ['type' => 'string', 'label' => 'Numéro Orange Money qui reçoit les paiements', 'default' => '', 'help' => 'Vide = Orange Money non proposé.'],
                    'payment_orange_holder' => ['type' => 'string', 'label' => 'Nom affiché du titulaire Orange Money', 'default' => ''],
                    'payment_moov_number' => ['type' => 'string', 'label' => 'Numéro Moov Money qui reçoit les paiements', 'default' => '', 'help' => 'Vide = Moov Money non proposé.'],
                    'payment_moov_holder' => ['type' => 'string', 'label' => 'Nom affiché du titulaire Moov Money', 'default' => ''],
                    'subscription_payment_instructions' => ['type' => 'text', 'label' => 'Instructions de paiement affichées au prestataire', 'default' => 'Envoyez le montant exact depuis votre téléphone au numéro indiqué, puis envoyez ici la capture d\'écran du message de confirmation, le numéro utilisé et la référence de la transaction. L\'équipe vérifie et active votre offre.'],
                    'subscription_reminder_days' => ['type' => 'int', 'label' => 'Prévenir le prestataire X jours avant l\'échéance', 'default' => 5],
                    'notify_admin_subscription_request' => ['type' => 'bool', 'label' => 'Alerter l\'admin à chaque demande d\'abonnement', 'default' => true],
                ],
            ],
            'shop' => [
                'label' => 'Gestion de boutique',
                'icon' => '🧾',
                'settings' => [
                    'shop_management_enabled' => ['type' => 'bool', 'label' => 'Espace de gestion des prestataires (stock, factures, dépenses, rapports)', 'default' => true],
                    'expense_categories' => ['type' => 'list', 'label' => 'Catégories de dépenses proposées', 'default' => ['Loyer', 'Salaires', 'Électricité et eau', 'Transport', 'Téléphone et Internet', 'Impôts et taxes', 'Outillage', 'Entretien', 'Autre']],
                    'multi_shop_enabled' => ['type' => 'bool', 'label' => 'Un commerçant peut déclarer plusieurs boutiques', 'default' => true],
                    'max_shops_per_owner' => ['type' => 'int', 'label' => 'Boutiques max. par commerçant', 'default' => 5],
                    'shop_teams_enabled' => ['type' => 'bool', 'label' => 'Les commerçants peuvent ajouter des employés (gérant, vendeur, caissier)', 'default' => true],
                    'max_team_members' => ['type' => 'int', 'label' => 'Employés max. par boutique', 'default' => 10],
                    'invoice_prefix' => ['type' => 'string', 'label' => 'Préfixe des factures', 'default' => 'F'],
                    'quote_prefix' => ['type' => 'string', 'label' => 'Préfixe des devis', 'default' => 'D'],
                    'invoice_footer' => ['type' => 'text', 'label' => 'Mention en bas des factures', 'default' => 'Merci de votre confiance.'],
                ],
            ],
            'backups' => [
                'label' => 'Sauvegardes',
                'icon' => '💾',
                'settings' => [
                    'backup_auto_enabled' => ['type' => 'bool', 'label' => 'Sauvegarde automatique chaque nuit (2 h)', 'default' => false, 'help' => 'Nécessite la tâche planifiée Laravel sur le serveur (voir README).'],
                    'backup_include_files' => ['type' => 'bool', 'label' => 'Inclure les photos et fichiers envoyés', 'default' => true],
                    'backup_keep' => ['type' => 'int', 'label' => 'Nombre de sauvegardes conservées', 'default' => 10],
                    'backup_password' => ['type' => 'secret', 'label' => 'Mot de passe de chiffrement des sauvegardes (AES-256)', 'default' => '', 'help' => 'Conservez-le hors du serveur : sans lui, une sauvegarde chiffrée ne peut pas être restaurée.'],
                    'backup_pg_dump_path' => ['type' => 'string', 'label' => 'Dossier des outils PostgreSQL (pg_dump), vide = automatique', 'default' => ''],
                ],
            ],
            'security' => [
                'label' => 'Sécurité',
                'icon' => '🔐',
                'settings' => [
                    'admin_two_factor_enabled' => ['type' => 'bool', 'label' => 'Double vérification des administrateurs (code par email à chaque connexion)', 'default' => false, 'help' => 'Activez-la dès que l\'envoi d\'emails fonctionne (testez avec « email de test »).'],
                    'admin_idle_minutes' => ['type' => 'int', 'label' => 'Déconnexion automatique d\'un admin inactif (minutes, 0 = jamais)', 'default' => 30],
                    'employee_email_verification' => ['type' => 'bool', 'label' => 'Les employés de boutique doivent confirmer leur email avant d\'accéder à la boutique', 'default' => true],
                ],
            ],
            'assist' => [
                'label' => 'Dépannage en direct',
                'icon' => '🆘',
                'settings' => [
                    'assist_enabled' => ['type' => 'bool', 'label' => 'Dépannage en direct (le conducteur envoie sa position, les pros proches sont prévenus)', 'default' => true],
                    'assist_guests_allowed' => ['type' => 'bool', 'label' => 'Demande possible sans compte (nom et téléphone suffisent)', 'default' => true],
                    'assist_radius_km' => ['type' => 'int', 'label' => 'Rayon de recherche des prestataires (km)', 'default' => 10],
                    'assist_max_providers' => ['type' => 'int', 'label' => 'Nombre maximum de prestataires prévenus par demande', 'default' => 15],
                    'assist_expire_minutes' => ['type' => 'int', 'label' => 'Délai avant qu\'une demande sans réponse soit fermée (minutes)', 'default' => 45],
                ],
            ],
            'vehicles' => [
                'label' => 'Carnet d\'entretien et électrique',
                'icon' => '⚡',
                'settings' => [
                    'logbook_enabled' => ['type' => 'bool', 'label' => 'Carnet d\'entretien pour les clients (rappels de vidange, assurance, visite technique…)', 'default' => true],
                    'logbook_reminders_enabled' => ['type' => 'bool', 'label' => 'Envoyer les rappels d\'entretien (email et notification push) 7 jours avant l\'échéance', 'default' => true],
                    'electric_page_enabled' => ['type' => 'bool', 'label' => 'Page « Moto et voiture électriques » (spécialistes, points de recharge, conseils)', 'default' => true],
                    'electric_intro' => ['type' => 'text', 'label' => 'Texte d\'introduction de la page électrique', 'default' => 'Motos et voitures électriques arrivent au Burkina Faso, mais peu de réparateurs savent les entretenir. Trouvez ici les spécialistes vérifiés, les points de recharge et d\'échange de batterie, et les bons gestes en cas de panne.'],
                ],
            ],
            'emergency' => [
                'label' => 'Urgence',
                'icon' => '🚨',
                'settings' => [
                    'emergency_numbers' => ['type' => 'pairs', 'label' => 'Numéros d\'urgence (numéro | libellé)', 'default' => [
                        '17' => 'Police secours',
                        '18' => 'Sapeurs-pompiers',
                        '16' => 'Gendarmerie',
                    ], 'help' => 'Vérifiez ces numéros avant la mise en ligne.'],
                ],
            ],
        ];
    }

    /** Modules prévus mais pas encore développés : affichés dans l'admin, non activables. */
    public static function upcomingModules(): array
    {
        return [
            ['Paiement mobile en ligne', 'Orange Money, Moov Money : paiement automatique des abonnements (aujourd\'hui activés à la main par l\'admin).'],
            ['Codes SMS (OTP)', 'Vérification du téléphone et récupération de compte par SMS.'],
            ['Prix des carburants', 'Prix et disponibilité par station, mis à jour par les stations.'],
            ['Messagerie interne', 'Discussion client ↔ prestataire dans l\'application.'],
            ['Langues mooré et dioula', 'Traduction de l\'interface.'],
            ['Accès USSD', 'Recherche d\'un prestataire sans smartphone ni Internet.'],
            ['Application mobile Flutter', 'Utilise déjà l\'API /api/v1.'],
        ];
    }

    /** @return array<string, array> toutes les définitions à plat, indexées par clé */
    public static function all(): array
    {
        static $flat;

        return $flat ??= collect(self::groups())->flatMap(fn ($g) => $g['settings'])->all();
    }

    public static function defaults(): array
    {
        return array_map(fn ($def) => $def['default'], self::all());
    }
}
