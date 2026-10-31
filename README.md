# MaPanne

Plateforme de dépannage automobile et moto au Burkina Faso : trouver vite un mécanicien, un garage, un dépanneur, une station-service ou un vendeur de pièces **vérifié**, près de soi.

## Stack

| Couche | Choix |
|---|---|
| Backend | Laravel 12 (PHP 8.4), Sanctum (API par jeton) |
| Base de données | PostgreSQL 18 + PostGIS (recherche par distance indexée) |
| Interface | Blade + Tailwind CSS 4 + Alpine.js, pensée mobile d'abord |
| Carte | Leaflet + OpenStreetMap (chargée seulement sur les pages avec carte) |
| Mobile (futur) | Flutter, branché sur l'API `/api/v1` |

## Installation locale

```bash
composer install
npm install
cp .env.example .env            # puis renseigner DB_PASSWORD
php artisan key:generate
```

Créer les bases avec PostGIS (une seule fois) :

```sql
CREATE DATABASE mapanne001;      -- puis : CREATE EXTENSION postgis;
CREATE DATABASE mapanne001_test; -- puis : CREATE EXTENSION postgis;
```

```bash
php artisan migrate --seed
php artisan storage:link
npm run build                   # ou: npm run dev
php -S 127.0.0.1:8010 -t public server.php
```

> Le PHP de XAMPP (8.2, sans `pdo_pgsql`) ne convient pas : utiliser celui de Herd
> (`C:\Users\<vous>\.config\herd\bin\php84\php.exe`).

Tests : `php artisan test` (utilise la base `mapanne001_test`).

### Comptes de démonstration (environnement local uniquement)

Mot de passe : `password` — connexion par email (ou par téléphone si l'option est active).

| Rôle | Email | Téléphone |
|---|---|---|
| Super-administrateur | admin@mapanne.test | 70 00 00 00 |
| Client | client@mapanne.test | 70 00 00 01 |
| Prestataire en attente | pro@mapanne.test | 72 00 00 00 |
| Boutique de pièces validée (avec ventes, stock et dépenses de démo) | — | 71 00 00 06 |

⚠️ Hors environnement local, le seeder exige `ADMIN_PASSWORD` dans `.env`.

En local, les emails (confirmation, mot de passe oublié, notifications) sont écrits dans
`storage/logs/laravel.log` (`MAIL_MAILER=log`). En production, configurez le SMTP depuis
**Admin › Réglages › Serveur d'envoi d'emails** et testez avec le bouton prévu.

### Tâches planifiées (production)

Abonnements (échéances, rappels) et sauvegarde automatique passent par le planificateur Laravel. Une seule ligne cron :

```bash
* * * * * cd /chemin/vers/mapanne && php artisan schedule:run >> /dev/null 2>&1
```

Commandes manuelles : `php artisan mapanne:subscriptions`, `php artisan mapanne:backup --force`.
Les sauvegardes utilisent `pg_dump` / `pg_restore` (installés avec PostgreSQL) et sont rangées dans `storage/app/private/backups`.

📄 Récapitulatif fonctionnel, rôles, options d'administration et analyse : [docs/RECAPITULATIF.md](docs/RECAPITULATIF.md).

## Architecture

```
app/
  Enums/            Rôles, types de prestataires, statuts (validation, dispo, signalement)
  Models/           User, Provider, Service, Schedule, Review, Report, City, Neighborhood…
  Services/         ProviderSearch : recherche publique partagée web + API
  Http/Controllers/
    Auth/           Connexion par téléphone ou email, inscription client / pro
    Account/        Profil, véhicules, favoris (client)
    Pro/            Tableau de bord, fiche, horaires, photos (prestataire)
    Admin/          Validation, utilisateurs, modération, référentiels
    Api/            API REST v1 (Flutter)
  Http/Middleware/  EnsureRole (role:admin…), EnsureAccountIsActive
resources/views/    Blade : public, auth, account, pro, admin, composants
```

### Règles métier garanties (et testées)

- Un prestataire n'apparaît **jamais** au public tant que l'administrateur ne l'a pas validé (`Provider::scopeVisible`). Fiche en attente, refusée ou suspendue : 404 public, aperçu réservé au titulaire et à l'admin.
- Un compte suspendu est déconnecté immédiatement (sessions web et jetons API révoqués).
- Le rôle et le statut de validation ne sont jamais modifiables via un formulaire (non assignables en masse).
- Avis : réservés aux clients, un seul par client et par prestataire, 5 par heure maximum, note moyenne recalculée en excluant les avis masqués par la modération.
- Horaires : gestion des plages de nuit qui passent minuit et du 24h/24.
- Journal des actions d'administration (`activity_logs`).

## API v1

| Méthode | Route | Description |
|---|---|---|
| POST | `/api/v1/auth/register` | Inscription (+ `device_name`) → jeton |
| POST | `/api/v1/auth/login` | Connexion téléphone/email → jeton |
| GET | `/api/v1/auth/me` | Utilisateur courant (jeton) |
| POST | `/api/v1/auth/logout` | Révoque le jeton |
| GET | `/api/v1/references` | Types, catégories/services, villes/quartiers, rayons |
| GET | `/api/v1/providers` | Recherche : `lat`, `lng`, `radius`, `type`, `service`, `city`, `neighborhood`, `q`, `open_now`, `night`, `available` |
| GET | `/api/v1/providers/{slug}` | Fiche publique |
