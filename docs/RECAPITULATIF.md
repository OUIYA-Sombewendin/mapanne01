# MaPanne — Récapitulatif du projet

MaPanne est une plateforme web de dépannage automobile et moto au Burkina Faso, avec une application mobile prévue plus tard. Elle sert à deux choses :
- trouver un professionnel **vérifié** près de soi ;
- acheter des pièces détachées auprès de boutiques locales.

Les professionnels y gèrent aussi leur activité au quotidien : stock, ventes, factures, dépenses et bénéfices.

- **Stack** : Laravel 12 · PHP 8.4 · PostgreSQL 18 + PostGIS · Blade, Tailwind 4 et Alpine.js · Leaflet/OpenStreetMap · API REST `/api/v1` (Sanctum) pour la future app Flutter.
- **Identifiant** : l'**email**. Le **téléphone** vient en second : obligatoire pour les pros, facultatif pour les clients sauf si l'admin l'exige.

---

## 1. Les utilisateurs et leurs actions

### Visiteur (sans compte)
- Rechercher un prestataire :
  - autour de lui (GPS), par ville ou par quartier ;
  - en filtrant par type, service et distance ;
  - en choisissant « ouvert maintenant », « de nuit » ou « disponible ».
- Voir la carte et les fiches : horaires, avis, **informations propres au métier** (par exemple « se déplace », « dépanneuse 3,5 t », « carburants disponibles »).
- Appeler ou écrire sur WhatsApp en un geste.
- Parcourir le marketplace de pièces et contacter une boutique.
- Consulter la page Urgence et les pages d'information.

### Client
Tout ce que fait le visiteur, et en plus :
- gérer son compte et ses véhicules ;
- garder des prestataires en favoris ;
- laisser des avis et faire des signalements ;
- **demander une pièce introuvable** : les boutiques de sa ville lui font une offre.

### Prestataire (mécanicien, garage, dépanneur, station, boutique de pièces, et tout type ajouté par l'admin)
- **Espace pro** : même allure que l'admin (menu latéral, barre du haut avec recherche dans le catalogue, « Nouvelle vente », notifications « À traiter », mode sombre). Le tableau de bord affiche les ventes du mois, le bénéfice, le stock, la note, la disponibilité et les dernières ventes.
- **Fiche** : **un formulaire propre à chaque métier** (voir section 4), avec ses sections, ses mots et uniquement ses services. Elle n'est publiée qu'**après validation de l'admin**.
- **Le type d'activité ne se change pas depuis la fiche** : il faut passer par le support (l'admin peut le changer), ou déclarer une nouvelle boutique pour un commerçant.
- **Catalogue / inventaire** (vendeurs de pièces) :
  - tout le contenu de la boutique, publié ou non sur le marketplace ;
  - prix d'achat, seuil d'alerte, emplacement en rayon ;
  - import Excel/CSV.
- **Gestion** (nouveau menu « Gestion ») :
  - **Vue d'ensemble** : ventes du mois, marge, dépenses, **bénéfice net**, pièces à réapprovisionner, factures impayées.
  - **Stock** (vendeurs de pièces) :
    - entrées (achats, retours), sorties (vente au comptoir, perte, usage atelier), inventaire ;
    - historique de chaque mouvement ;
    - valeur du stock au prix d'achat et au prix de vente ;
    - le stock ne peut jamais devenir négatif.
  - **Ventes et factures** :
    - factures et **devis**, articles du stock ou prestations libres (main d'œuvre…) ;
    - remise et paiement partiel ou complet ;
    - impression ou PDF, envoi par WhatsApp ;
    - un devis se transforme en facture en un clic ;
    - une facture annulée remet les pièces en stock.
  - **Dépenses** : loyer, salaires, électricité… (catégories définies par l'admin).
  - **Rapports** :
    - chiffre d'affaires, coût des pièces vendues, marge brute, dépenses, bénéfice net, reste à encaisser ;
    - meilleures ventes et évolution ;
    - **export Excel** des ventes, des dépenses et du stock.
- **Abonnement** (si l'admin l'active) : voir les offres, demander une offre, suivre l'échéance.
- **Commerçant multi-boutiques** (vendeurs de pièces) :
  - déclarer plusieurs boutiques (**Mes boutiques › Déclarer une boutique**), chacune validée par l'admin et avec sa propre fiche, son stock, ses ventes et ses dépenses ;
  - passer d'une boutique à l'autre avec le sélecteur « Boutique active » ;
  - nombre maximum de boutiques réglable dans l'admin (et par offre d'abonnement).
- **Équipe** (propriétaire uniquement) : ajouter des employés avec leur propre compte. Chaque vente indique qui l'a faite.

  | Rôle | Accès |
  |---|---|
  | Gérant | Tout sauf l'équipe, l'abonnement et l'ajout de boutiques |
  | Vendeur | Catalogue, stock, ventes et factures, demandes de pièces |
  | Caissier | Ventes et factures uniquement |

  L'employé reçoit un email pour choisir son mot de passe, ou le propriétaire lui donne un mot de passe provisoire. Un employé retiré perd immédiatement l'accès.

### Administrateur (selon son rôle)
- **Tableau de bord** : bandeau d'accueil, cartes par type de prestataire, indicateurs, file « à traiter », dernières actions.
- Validation et modération des prestataires, des avis, des signalements, des produits et des demandes de pièces.
- **Abonnements** : composer les offres, activer une demande après paiement, attribuer une offre, suivre les échéances.
- **Rôles et permissions** : créer des rôles et nommer des administrateurs avec un rôle précis.
- **Configuration** :
  - apparence et couleurs, page d'accueil, réglages ;
  - **sauvegardes**, **historique**, **import et export**.

---

## 2. Rôles et permissions

| Rôle livré | Peut… |
|---|---|
| Super-administrateur | Tout, y compris rôles, sauvegardes et restauration (non modifiable, non supprimable) |
| Modérateur | Valider les prestataires, modérer avis, signalements et produits, voir l'historique |
| Commercial | Voir les prestataires, gérer les abonnements, exporter |
| Support | Voir les comptes, bloquer ou débloquer, confirmer un email, **accéder au compte** d'un utilisateur pour l'aider |

- Les **18 permissions** sont combinables librement dans **Utilisateurs › Rôles et permissions**.
- Pour ajouter un membre à l'équipe : il crée un compte client, puis l'admin lui attribue un rôle dans **Comptes**.
- Garde-fous :
  - on ne peut pas modifier son propre rôle ;
  - il reste toujours au moins un super-admin ;
  - seul un super-admin peut nommer un super-admin ou suspendre un administrateur.
- Le menu n'affiche que ce que le rôle permet, et chaque route est protégée côté serveur.

### Bloquer un compte, accéder à un compte, verrous
- **Bloquer / débloquer** (permission `users.manage`) : la personne est déconnectée partout (site et application) et ne peut plus se connecter.
- **Accéder au compte** (permission `users.impersonate`, bouton « 👁 Accéder » dans Comptes) :
  - l'admin voit exactement l'espace de la personne (client ou pro), pour l'aider ;
  - un bandeau orange permanent propose « Revenir à mon compte admin » ;
  - impossible sur un autre administrateur ou sur un compte bloqué ;
  - le mot de passe de la personne ne peut pas être changé pendant l'accès ;
  - le début et la fin de l'accès sont notés dans l'historique.
- **Verrous anti-erreur** (permission `locks.manage`, bouton 🔓 dans les listes) :
  - éléments verrouillables : prestataires, comptes, produits, avis, types, services, villes, pages, offres, rôles ;
  - un élément verrouillé ne peut plus être modifié, validé, suspendu, bloqué ni supprimé depuis l'admin, même par erreur ;
  - un motif peut être noté ; la page **Configuration › Verrous** liste tous les verrous ;
  - le verrou ne gêne pas l'utilisateur concerné (un pro verrouillé peut toujours mettre à jour sa fiche) ;
  - chaque verrouillage et déverrouillage est noté dans l'historique.

---

## 3. Abonnements

- **Désactivés par défaut** : tout est gratuit pour tous.
- Une fois activés dans Réglages › Abonnements, ce qu'un prestataire peut utiliser dépend de son offre :
  - catalogue, import, demandes de pièces ;
  - stock, factures, dépenses, rapports ;
  - statistiques et mise à la une ;
  - nombre maximum de produits.
- Trois offres sont livrées et entièrement modifiables :

  | Offre | Prix |
  |---|---|
  | Gratuit | 0 |
  | Standard | 5 000 FCFA par mois |
  | Premium | 15 000 FCFA par mois, avec mise à la une |

  Une offre peut être réservée à certains types (par exemple une offre « Garage Pro »).
- **Paiement** : pas encore de paiement en ligne.
  1. Le prestataire choisit une offre et voit les instructions de paiement, réglables par l'admin.
  2. L'admin active l'offre après réception et note le montant et la référence.
  3. Une offre gratuite s'active tout de suite.
- **Échéances** : chaque jour, les abonnements échus repassent sur l'offre par défaut et un rappel part X jours avant l'échéance, par email.
- Une fonctionnalité non incluse affiche un cadenas 🔒 et renvoie vers la page Abonnement.

---

## 4. Tables séparées par type de prestataire

La table `providers` garde le **tronc commun** : nom, contacts, position GPS, validation, avis. C'est ce qui permet **une seule recherche par distance** pour tous les métiers. Les informations propres à chaque métier sont dans **leur propre table** :

| Table | Contenu |
|---|---|
| `mechanic_profiles` | se déplace, distance, expérience, spécialités, véhicules, marques |
| `garage_profiles` | places, mécaniciens, diagnostic, pont, tôlerie, assurances, spécialités |
| `tow_truck_profiles` | dépanneuses, tonnage, véhicules, zone, prise en charge, prix au km, 24 h/24 |
| `station_profiles` | enseigne, carburants, 24 h/24, boutique, lavage, gonflage, lubrifiants, mécanicien |
| `shop_profiles` | véhicules couverts, marques, détail ou gros, occasion, commande sur demande |

Dans **Types de prestataires**, l'admin rattache chaque type à une fiche métier. Par exemple, un nouveau type « Vulcanisateur » peut utiliser la fiche « Mécanicien ».

### Un formulaire par métier
Chaque métier a son propre formulaire (`resources/views/pro/profile/<métier>.blade.php`), sans lien avec les autres :

| Métier | Sections du formulaire |
|---|---|
| Mécanicien | Qui je suis → Mon savoir-faire → Prestations → Zone d'intervention → Paiement → Statut légal |
| Garage | Le garage → Atelier et équipements → Prestations de l'atelier → Adresse → Entreprise → Paiement |
| Dépanneur | Service de dépannage → Flotte → Tarifs → Interventions → Base et zone couverte |
| Station | La station et son enseigne → Carburants → Services sur place → Adresse → Paiement → Entreprise |
| Boutique | La boutique → Ce que vous vendez → Catalogue et rayons → Adresse → Paiement et livraison → Entreprise |

- Un type sans fiche métier utilise un formulaire général.
- **Services réservés au métier** : un garage ne voit ni ne peut enregistrer « Super 91 ». Le serveur refuse les services d'un autre métier.
- Le **formulaire admin** de modification suit aussi le métier. Le changement de métier y est isolé dans une zone d'avertissement : il retire les services de l'ancien métier et il est noté dans l'historique.
- **Fiche publique par métier** :
  - station : carburants et services sur place ;
  - dépanneur : bouton « Appeler le dépanneur », prise en charge, prix au km, zone et flotte ;
  - garage : places, mécaniciens et équipements ;
  - mécanicien : se déplace ou non, expérience, spécialités ;
  - boutique : ce qu'elle vend, avec son catalogue affiché en premier.

### Champs ajoutés par l'admin (Référentiels › Formulaires des métiers)
- On ajoute des champs à un métier précis ou à tous les métiers. Six types de réponse : texte court, texte long, nombre, oui/non, liste à un choix, cases à cocher.
- Options : obligatoire, public ou interne (visible seulement par l'équipe), actif, ordre.
- Les champs apparaissent tout de suite dans la section « Autres informations » du formulaire pro. Les réponses sont visibles dans la fiche admin et, pour les champs publics, sur la fiche publique.
- Supprimer un champ le retire des formulaires mais conserve les réponses déjà saisies. Chaque modification est notée dans l'historique.

Dans le menu admin, la section **Prestataires** liste chaque type séparément.

---

## 5. Ce qui se gère depuis l'admin, sans toucher au code

| Domaine | Réglages et options |
|---|---|
| Général | Nom, slogan, contacts du support, bannière d'annonce, mode maintenance |
| **Apparence et couleurs** | Couleur principale, couleur sombre (toutes les nuances sont calculées automatiquement), logo, aperçu |
| **Page d'accueil** | Titres, sous-titre, raccourcis « problèmes fréquents », sections affichées ou masquées |
| Comptes | Inscriptions ouvertes ou fermées, connexion par téléphone, email vérifié obligatoire |
| Recherche | Rayons, centre de carte, mise à la une |
| Prestataires | Photos, contrôle des changements sensibles |
| Avis | Activation, pré-modération, limites, motifs de signalement |
| Marketplace | Activation, pré-modération, occasion, stock affiché, **livraison (désactivée par défaut)**, paiements, demandes de pièces, import |
| **Abonnements** | Activation, instructions de paiement, rappel avant échéance, alerte admin |
| **Gestion de boutique** | Activation, catégories de dépenses, préfixes des factures et devis, mention en bas de facture |
| **Sauvegardes** | Sauvegarde automatique, inclusion des photos, nombre conservé, chemin de pg_dump |
| Notifications et emails | Choix de chaque email, serveur SMTP, email de test |
| Urgence | Numéros affichés |
| Référentiels | Types (avec fiche métier), services, catégories de pièces, villes et quartiers, pages |
| **Offres** | Prix, durée, fonctionnalités, limites, types concernés, offre par défaut |
| **Rôles** | Permissions de chaque rôle |

### Outils d'administration
- **Sauvegardes** :
  - une archive .zip contient la base complète (`pg_dump`) et les photos ;
  - téléchargement et suppression ;
  - **restauration** réservée au super-admin, à confirmer en tapant RESTAURER ; elle se fait dans une transaction, après une sauvegarde de sécurité automatique ;
  - sauvegarde automatique chaque nuit (si activée).
- **Historique** :
  - toutes les actions de l'équipe : validations, modération, réglages, rôles, abonnements, sauvegardes, imports et exports, connexions à l'admin ;
  - filtres par domaine, par membre et par dates ;
  - export Excel.
- **Import et export** :
  - exports CSV (Excel) : prestataires, utilisateurs, produits, avis, abonnements, zones ; les réglages s'exportent en JSON, **sans mots de passe ni secrets** ;
  - imports : villes et quartiers, services, catégories de pièces, catalogue d'une boutique, réglages JSON.
- **Recherche globale** dans la barre du haut : prestataires, utilisateurs, pièces.
- **Listes harmonisées** (admin et espace pro) :
  - filtres appliqués **dès qu'on choisit une valeur** (la recherche texte s'applique après une courte pause) ;
  - compteur de filtres actifs et bouton « Réinitialiser » ;
  - **tri en cliquant sur les en-têtes de colonnes** (un second clic inverse l'ordre).
  - Filtres ajoutés par liste :

    | Liste | Filtres |
    |---|---|
    | Prestataires | ville, nuit, entreprise, photos, compte bloqué, date d'inscription |
    | Comptes | rôle admin, statut, email confirmé, ville, dernière connexion, date d'inscription |
    | Pièces | boutique, état, stock, prix minimum et maximum |
    | Avis | note, avec commentaire, recherche, date |
    | Signalements | motif, fiche ou avis, recherche, date |
    | Abonnements | offre, moyen de paiement |
    | Catalogue pro | catégorie, état, promo, à réapprovisionner, masqués |
    | Stock | catégorie, rayon, stock à zéro, non publiées |
    | Mouvements de stock | motif, dates |
    | Factures | paiement, vendeur, dates |
    | Dépenses | catégorie, paiement, recherche |
- **Mode sombre** et menu réductible, mémorisés sur l'appareil ; le menu défile tout seul jusqu'à la page ouverte.
- **Cloche de notifications** : ce qui attend une action.

---

## 6. Analyse de la logique

### Règles garanties par les tests (100 tests)
- **Visibilité** : un prestataire non validé est invisible partout. Un produit n'est public que si la boutique est visible et que son type peut vendre.
- **Permissions** :
  - chaque section admin vérifie la permission côté serveur ;
  - un admin sans rôle n'a accès qu'au tableau de bord ;
  - le dernier super-admin est protégé.
- **Stock** :
  - toujours tracé, jamais négatif ;
  - une facture fait sortir les pièces du stock et son annulation les y remet ;
  - un devis ne touche pas au stock tant qu'il n'est pas transformé ;
  - numéros de facture consécutifs, sans doublon même en cas de double clic.
- **Verrous et accès** : élément verrouillé = action admin refusée ; accès au compte refusé sans permission, sur un admin ou un compte bloqué ; retour au compte admin vérifié.
- **Cloisonnement** : les factures, le stock et les dépenses d'un pro sont invisibles pour les autres pros.
- **Rapports** : marge = ventes − prix d'achat au moment de la vente ; bénéfice net = marge − dépenses (vérifié par calcul).
- **Abonnements** : demande, activation, déblocage des fonctionnalités, mise à la une, échéance et retour à l'offre par défaut.
- **Données sensibles** :
  - les exports ne contiennent ni mot de passe ni secret ;
  - l'import de réglages ignore les clés inconnues et les secrets ;
  - les cellules CSV sont protégées contre l'injection de formules Excel.

### Points à surveiller
- **Paiement des abonnements** : il reste manuel. Le paiement mobile automatique est un module à développer, et l'intégration Orange Money ou Moov demande un contrat marchand.
- **Restauration** : elle remplace aussi les sessions, donc l'admin peut devoir se reconnecter. Elle doit rester rare et se faire en dehors des heures de pointe.
- **Mise à la une** : celle obtenue par un abonnement Premium est retirée à l'échéance, même si l'admin l'avait aussi activée à la main.
- **Fiches métier** : si un pro change de type, la fiche métier de son ancien type est conservée mais n'est plus affichée.
- **Photos** : toutes les images envoyées (fiches, produits, demandes de pièces, logo) sont redressées, réduites et réencodées en WebP. Leurs métadonnées, dont la position GPS, sont supprimées. Les photos envoyées **avant** cette version ne sont pas retraitées.
- **Recherche** : des index trigrammes (`pg_trgm`) accélèrent la recherche par mots. Si l'hébergeur refuse l'extension, la recherche fonctionne quand même, en plus lent.
- **Points déjà signalés** :
  - avis sans preuve d'intervention ;
  - demandes de pièces diffusées par ville et non par rayon.

---

## 7. Marketplace et gestion : face à la concurrence

| Attente | Ce que fait MaPanne |
|---|---|
| Client : trouver vite | Recherche par mots, véhicule, distance ; demandes de pièces avec offres des boutiques |
| Client : confiance | Boutiques vérifiées, avis, garantie, signalement |
| Boutique : vendre plus | Catalogue, mise à la une, demandes clients, statistiques |
| Boutique : **gérer sa boutique** | Inventaire complet, mouvements de stock, alertes, factures et devis imprimables, dépenses, bénéfices, export Excel, tout depuis le téléphone |
| Plateforme : revenus | Abonnements par offre, réglables sans développement |
| Mise en relation | Livraison **désactivée par défaut** : MaPanne met en relation, et l'admin peut réactiver la livraison à tout moment |

**Prochaines étapes conseillées** :
1. Paiement mobile pour les abonnements.
2. Compression des photos.
3. Plusieurs employés par boutique (vendeur ou caissier).
4. Caisse rapide (scan de codes-barres).
5. Fournisseurs et bons de commande.
6. Tableaux de bord exportables en PDF.
