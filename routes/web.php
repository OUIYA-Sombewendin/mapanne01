<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AssistController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\ElectricController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PartRequestController;
use App\Http\Controllers\Pro;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Public -----------------------------------------------------------------------
Route::get('/', HomeController::class)->name('home');
Route::get('/recherche', SearchController::class)->name('search');
Route::get('/prestataires/{provider}', [ProviderController::class, 'show'])->name('providers.show');
Route::post('/prestataires/{provider}/contact', [ProviderController::class, 'contact'])->middleware('throttle:30,1')->name('providers.contact');
Route::view('/urgence', 'pages.emergency')->name('emergency');
Route::get('/electrique', ElectricController::class)->middleware('feature:electric_page_enabled')->name('electric');

// Dépannage en direct : le conducteur envoie sa position, les pros proches sont prévenus.
Route::middleware('feature:assist_enabled')->group(function () {
    Route::get('/depannage', [AssistController::class, 'create'])->name('assist.create');
    Route::post('/depannage', [AssistController::class, 'store'])->middleware('throttle:5,10')->name('assist.store');
    Route::get('/depannage/{assist}', [AssistController::class, 'show'])->name('assist.show');
    Route::get('/depannage/{assist}/etat', [AssistController::class, 'status'])->middleware('throttle:60,1')->name('assist.status');
    Route::post('/depannage/{assist}/annuler', [AssistController::class, 'cancel'])->name('assist.cancel');
});
Route::get('/pages/{page}', [PageController::class, 'show'])->name('pages.show');

// Marketplace de pièces détachées (désactivable dans l'admin)
Route::middleware('feature:marketplace_enabled')->group(function () {
    Route::get('/pieces', [MarketplaceController::class, 'index'])->name('products.index');
    Route::get('/pieces/{product}', [MarketplaceController::class, 'show'])->name('products.show');
    Route::post('/pieces/{product}/contact', [MarketplaceController::class, 'contact'])->middleware('throttle:30,1')->name('products.contact');
});

// Authentification -------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:20,1');
    // Double vérification des administrateurs (code reçu par email)
    Route::get('/connexion/verification', [TwoFactorController::class, 'show'])->name('login.two-factor');
    Route::post('/connexion/verification', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/connexion/verification/renvoyer', [TwoFactorController::class, 'resend'])->middleware('throttle:3,1')->name('login.two-factor.resend');
    Route::get('/inscription', [RegisterController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/mot-de-passe-oublie', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/espace', fn () => redirect(auth()->user()->homeRoute()))->name('dashboard');

    // Vérification de l'email
    Route::get('/email/verifier', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verifier/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/renvoyer', [VerificationController::class, 'resend'])->middleware('throttle:3,1')->name('verification.send');

    // Profil et mot de passe : tous les rôles (véhicules, favoris et avis réservés aux clients)
    Route::get('/mon-compte', [Account\AccountController::class, 'show'])->name('account.show');
    // Pendant l'accès d'un admin au compte (assistance), l'email et le mot de passe ne peuvent pas être changés.
    Route::put('/mon-compte', [Account\AccountController::class, 'update'])->middleware('not-impersonating')->name('account.update');
    Route::put('/mon-compte/mot-de-passe', [Account\AccountController::class, 'updatePassword'])->middleware('not-impersonating')->name('account.password');

    // Notifications push de cet appareil
    Route::post('/notifications/push', [PushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->name('push.store');
    Route::delete('/notifications/push', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');

    // Fin de l'accès d'un admin au compte d'un utilisateur (bandeau « Revenir à mon compte »).
    Route::post('/acces/quitter', [Admin\ImpersonationController::class, 'stop'])->name('impersonation.stop');

    // Actions qui exigent un email vérifié si l'option est activée dans l'admin
    Route::middleware('verified.if')->group(function () {
        Route::post('/prestataires/{provider}/signaler', [ReportController::class, 'store'])->middleware('throttle:10,60')->name('providers.report');

        // Espace client -------------------------------------------------------
        Route::middleware('role:client')->group(function () {
            Route::post('/prestataires/{provider}/avis', [ReviewController::class, 'store'])->name('reviews.store');
            Route::delete('/prestataires/{provider}/avis', [ReviewController::class, 'destroy'])->name('reviews.destroy');
            Route::post('/prestataires/{provider}/favori', [Account\FavoriteController::class, 'toggle'])->name('favorites.toggle');
            Route::post('/mon-compte/vehicules', [Account\VehicleController::class, 'store'])->name('vehicles.store');
            Route::put('/mon-compte/vehicules/{vehicle}', [Account\VehicleController::class, 'update'])->name('vehicles.update');
            Route::delete('/mon-compte/vehicules/{vehicle}', [Account\VehicleController::class, 'destroy'])->name('vehicles.destroy');

            // Carnet d'entretien
            Route::middleware('feature:logbook_enabled')->group(function () {
                Route::get('/mon-carnet', [Account\LogbookController::class, 'index'])->name('logbook.index');
                Route::get('/mon-carnet/{vehicle}', [Account\LogbookController::class, 'show'])->name('logbook.show');
                Route::put('/mon-carnet/{vehicle}/rappels', [Account\LogbookController::class, 'reminders'])->name('logbook.reminders');
                Route::post('/mon-carnet/{vehicle}/entretiens', [Account\LogbookController::class, 'storeLog'])->name('logbook.logs.store');
                Route::delete('/mon-carnet/{vehicle}/entretiens/{log}', [Account\LogbookController::class, 'destroyLog'])->name('logbook.logs.destroy');
            });

            Route::middleware(['feature:marketplace_enabled', 'feature:part_requests_enabled'])->group(function () {
                Route::get('/mes-demandes', [PartRequestController::class, 'index'])->name('part-requests.index');
                Route::get('/mes-demandes/nouvelle', [PartRequestController::class, 'create'])->name('part-requests.create');
                Route::post('/mes-demandes', [PartRequestController::class, 'store'])->name('part-requests.store');
                Route::get('/mes-demandes/{partRequest}', [PartRequestController::class, 'show'])->name('part-requests.show');
                Route::post('/mes-demandes/{partRequest}/cloturer', [PartRequestController::class, 'close'])->name('part-requests.close');
            });
        });

        // Espace prestataire --------------------------------------------------
        // Tout passe par la boutique active ($user->provider) ; « shop:zone » vérifie le rôle du compte
        // dans cette boutique (propriétaire, gérant, vendeur, caissier : voir App\Support\ShopTeam).
        Route::middleware(['role:provider', 'shop'])->prefix('pro')->name('pro.')->group(function () {
            Route::get('/', [Pro\DashboardController::class, 'index'])->name('dashboard');
            Route::put('/disponibilite', [Pro\DashboardController::class, 'availability'])->middleware('shop:availability')->name('availability');

            // Dépannage en direct : demandes des conducteurs en panne à proximité
            Route::middleware(['shop:assist', 'feature:assist_enabled'])->group(function () {
                Route::get('/depannages', [Pro\AssistController::class, 'index'])->name('assist.index');
                Route::get('/depannages/compteur', [Pro\AssistController::class, 'count'])->name('assist.count');
                Route::post('/depannages/{assist}/accepter', [Pro\AssistController::class, 'accept'])->middleware('throttle:30,1')->name('assist.accept');
                Route::post('/depannages/{assist}/etape', [Pro\AssistController::class, 'update'])->name('assist.update');
            });

            Route::get('/statistiques', [Pro\StatsController::class, 'index'])->middleware(['shop:stats', 'plan:statistics'])->name('stats');

            // Boutiques du commerçant et changement de boutique active
            Route::get('/boutiques', [Pro\ShopController::class, 'index'])->name('shops.index');
            Route::post('/boutiques/{provider}/choisir', [Pro\ShopController::class, 'switch'])->name('shops.switch');
            Route::middleware(['shop:shops', 'feature:multi_shop_enabled'])->group(function () {
                Route::get('/boutiques/nouvelle', [Pro\ShopController::class, 'create'])->name('shops.create');
                Route::post('/boutiques', [Pro\ShopController::class, 'store'])->middleware('throttle:10,1')->name('shops.store');
            });

            // Équipe de la boutique active (réservé au propriétaire)
            Route::middleware(['shop:team', 'feature:shop_teams_enabled'])->group(function () {
                Route::get('/equipe', [Pro\TeamController::class, 'index'])->name('team.index');
                Route::post('/equipe', [Pro\TeamController::class, 'store'])->middleware('throttle:20,1')->name('team.store');
                Route::put('/equipe/{member}', [Pro\TeamController::class, 'update'])->name('team.update');
                Route::delete('/equipe/{member}', [Pro\TeamController::class, 'destroy'])->name('team.destroy');
            });

            Route::middleware('shop:profile')->group(function () {
                Route::post('/renvoyer', [Pro\DashboardController::class, 'resubmit'])->name('resubmit');
                Route::get('/fiche', [Pro\ProfileController::class, 'edit'])->name('profile');
                Route::put('/fiche', [Pro\ProfileController::class, 'update'])->name('profile.update');
                Route::get('/horaires', [Pro\ScheduleController::class, 'edit'])->name('schedule');
                Route::get('/badges', [Pro\VerificationController::class, 'index'])->name('verification');
                Route::post('/badges', [Pro\VerificationController::class, 'store'])->middleware('throttle:10,1')->name('verification.store');
                Route::delete('/badges/{document}', [Pro\VerificationController::class, 'destroy'])->name('verification.destroy');
                Route::put('/horaires', [Pro\ScheduleController::class, 'update'])->name('schedule.update');
                Route::post('/photos', [Pro\PhotoController::class, 'store'])->name('photos.store');
                Route::delete('/photos/{photo}', [Pro\PhotoController::class, 'destroy'])->name('photos.destroy');
            });

            // Catalogue : l'inventaire de la boutique (publié ou non sur le marketplace)
            Route::middleware(['shop:catalog', 'plan:catalog'])->group(function () {
                Route::get('/catalogue', [Pro\ProductController::class, 'index'])->name('products.index');
                Route::get('/catalogue/nouveau', [Pro\ProductController::class, 'create'])->name('products.create');
                Route::post('/catalogue', [Pro\ProductController::class, 'store'])->name('products.store');
                Route::middleware(['feature:catalog_import_enabled', 'plan:catalog_import'])->group(function () {
                    Route::get('/catalogue/import', [Pro\ProductController::class, 'importForm'])->name('products.import');
                    Route::post('/catalogue/import', [Pro\ProductController::class, 'import'])->middleware('throttle:10,1');
                    Route::get('/catalogue/modele.csv', [Pro\ProductController::class, 'template'])->name('products.template');
                });
                Route::get('/catalogue/{product}/modifier', [Pro\ProductController::class, 'edit'])->name('products.edit');
                Route::put('/catalogue/{product}', [Pro\ProductController::class, 'update'])->name('products.update');
                Route::patch('/catalogue/{product}/stock', [Pro\ProductController::class, 'quickUpdate'])->name('products.quick');
                Route::delete('/catalogue/{product}', [Pro\ProductController::class, 'destroy'])->name('products.destroy');
                Route::delete('/catalogue/photos/{photo}', [Pro\ProductController::class, 'destroyPhoto'])->name('products.photos.destroy');
            });

            Route::middleware(['shop:requests', 'feature:marketplace_enabled', 'feature:part_requests_enabled', 'plan:part_requests'])->group(function () {
                Route::get('/demandes', [Pro\PartRequestController::class, 'index'])->name('part-requests.index');
                Route::post('/demandes/{partRequest}/offre', [Pro\PartRequestController::class, 'offer'])->middleware('throttle:30,1')->name('part-requests.offer');
            });

            // Abonnement (offres définies dans l'admin)
            Route::middleware(['shop:subscription', 'feature:subscriptions_enabled'])->group(function () {
                Route::get('/abonnement', [Pro\SubscriptionController::class, 'show'])->name('subscription');
                Route::post('/abonnement', [Pro\SubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('subscription.store');
                Route::get('/abonnement/{subscription}/paiement', [Pro\SubscriptionController::class, 'pay'])->name('subscription.pay');
                Route::post('/abonnement/{subscription}/paiement', [Pro\SubscriptionController::class, 'proof'])->middleware('throttle:10,1')->name('subscription.proof');
                Route::delete('/abonnement/{subscription}', [Pro\SubscriptionController::class, 'cancel'])->name('subscription.cancel');
            });

            // Gestion : stock, ventes et factures, dépenses, rapports
            Route::middleware('feature:shop_management_enabled')->prefix('gestion')->name('manage.')->group(function () {
                Route::get('/', [Pro\ManagementController::class, 'index'])->middleware('shop:reports')->name('index');

                Route::middleware(['shop:stock', 'plan:stock'])->group(function () {
                    Route::get('/stock', [Pro\StockController::class, 'index'])->name('stock');
                    Route::post('/stock', [Pro\StockController::class, 'store'])->name('stock.store');
                    Route::get('/stock/mouvements', [Pro\StockController::class, 'movements'])->name('stock.movements');
                    Route::get('/stock/inventaires', [Pro\StockCountController::class, 'index'])->name('counts');
                    Route::post('/stock/inventaires', [Pro\StockCountController::class, 'store'])->name('counts.store');
                    Route::get('/stock/inventaires/{count}', [Pro\StockCountController::class, 'show'])->name('counts.show');
                    Route::put('/stock/inventaires/{count}', [Pro\StockCountController::class, 'update'])->name('counts.update');
                    Route::post('/stock/inventaires/{count}/valider', [Pro\StockCountController::class, 'apply'])->name('counts.apply');
                    Route::delete('/stock/inventaires/{count}', [Pro\StockCountController::class, 'destroy'])->name('counts.destroy');
                });

                Route::middleware(['shop:invoices', 'plan:invoices'])->group(function () {
                    Route::get('/factures', [Pro\InvoiceController::class, 'index'])->name('invoices.index');
                    Route::get('/factures/nouvelle', [Pro\InvoiceController::class, 'create'])->name('invoices.create');
                    Route::post('/factures', [Pro\InvoiceController::class, 'store'])->name('invoices.store');
                    Route::get('/factures/{invoice}', [Pro\InvoiceController::class, 'show'])->name('invoices.show');
                    Route::get('/factures/{invoice}/imprimer', [Pro\InvoiceController::class, 'print'])->name('invoices.print');
                    Route::post('/factures/{invoice}/paiement', [Pro\InvoiceController::class, 'payment'])->name('invoices.payment');
                    Route::post('/factures/{invoice}/annuler', [Pro\InvoiceController::class, 'cancel'])->name('invoices.cancel');
                    Route::post('/factures/{invoice}/convertir', [Pro\InvoiceController::class, 'convert'])->name('invoices.convert');
                });

                Route::middleware(['shop:expenses', 'plan:expenses'])->group(function () {
                    Route::get('/depenses', [Pro\ExpenseController::class, 'index'])->name('expenses.index');
                    Route::post('/depenses', [Pro\ExpenseController::class, 'store'])->name('expenses.store');
                    Route::delete('/depenses/{expense}', [Pro\ExpenseController::class, 'destroy'])->name('expenses.destroy');
                });

                Route::middleware(['shop:reports', 'plan:reports'])->group(function () {
                    Route::get('/rapports', [Pro\ReportController::class, 'index'])->name('reports');
                    Route::get('/rapports/export/{dataset}', [Pro\ReportController::class, 'export'])->whereIn('dataset', ['ventes', 'depenses', 'stock'])->name('reports.export');
                });
            });
        });
    });

    // Espace administrateur ---------------------------------------------------
    // Chaque section exige une permission (App\Support\Permissions), accordée par le rôle de l'admin.
    Route::middleware(['role:admin', 'admin.idle'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('/recherche', Admin\SearchController::class)->name('search');

        Route::middleware('can:providers.view')->group(function () {
            Route::get('/prestataires', [Admin\ProviderController::class, 'index'])->name('providers.index');
            Route::get('/metiers/{type:slug}', [Admin\TradeController::class, 'show'])->name('trades.show');
            Route::get('/depannages', [Admin\AssistController::class, 'index'])->name('assist.index');
            Route::get('/verifications', [Admin\VerificationController::class, 'index'])->name('verifications.index');
            Route::get('/verifications/{document}/fichier', [Admin\VerificationController::class, 'file'])->name('verifications.file');
            Route::get('/prestataires/{provider}', [Admin\ProviderController::class, 'show'])->name('providers.show');
        });
        // Verrous anti-erreur : les actions d'administration (middleware « unlocked ») refusent un élément verrouillé.
        Route::middleware('can:locks.manage')->group(function () {
            Route::get('/verrous', [Admin\LockController::class, 'index'])->name('locks.index');
            Route::post('/verrous', [Admin\LockController::class, 'toggle'])->name('locks.toggle');
        });

        Route::post('/utilisateurs/{user}/acceder', [Admin\ImpersonationController::class, 'start'])
            ->middleware(['can:users.impersonate', 'throttle:20,1'])->name('users.impersonate');

        Route::middleware(['can:providers.manage', 'unlocked'])->group(function () {
            Route::get('/prestataires/{provider}/modifier', [Admin\ProviderController::class, 'edit'])->name('providers.edit');
            Route::put('/prestataires/{provider}', [Admin\ProviderController::class, 'update'])->name('providers.update');
            Route::post('/depannages/{assist}/fermer', [Admin\AssistController::class, 'close'])->name('assist.close');
            Route::post('/verifications/{document}', [Admin\VerificationController::class, 'review'])->name('verifications.review');
            Route::get('/points-electriques', [Admin\ChargePointController::class, 'index'])->name('charge-points.index');
            Route::post('/points-electriques', [Admin\ChargePointController::class, 'store'])->name('charge-points.store');
            Route::put('/points-electriques/{point}', [Admin\ChargePointController::class, 'update'])->name('charge-points.update');
            Route::delete('/points-electriques/{point}', [Admin\ChargePointController::class, 'destroy'])->name('charge-points.destroy');
            Route::post('/prestataires/{provider}/valider', [Admin\ProviderController::class, 'approve'])->name('providers.approve');
            Route::post('/prestataires/{provider}/refuser', [Admin\ProviderController::class, 'reject'])->name('providers.reject');
            Route::post('/prestataires/{provider}/suspendre', [Admin\ProviderController::class, 'suspend'])->name('providers.suspend');
            Route::post('/prestataires/{provider}/reactiver', [Admin\ProviderController::class, 'reactivate'])->name('providers.reactivate');
            Route::post('/prestataires/{provider}/verifie', [Admin\ProviderController::class, 'markReviewed'])->name('providers.reviewed');
            Route::post('/prestataires/{provider}/une', [Admin\ProviderController::class, 'toggleFeatured'])->name('providers.featured');
        });

        Route::get('/utilisateurs', [Admin\UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
        Route::middleware(['can:users.manage', 'unlocked'])->group(function () {
            Route::post('/utilisateurs/{user}/statut', [Admin\UserController::class, 'toggle'])->name('users.toggle');
            Route::post('/utilisateurs/{user}/email-verifie', [Admin\UserController::class, 'verifyEmail'])->name('users.verify-email');
        });
        Route::middleware('can:roles.manage')->group(function () {
            Route::post('/utilisateurs/{user}/role', [Admin\UserController::class, 'role'])->middleware('unlocked')->name('users.role');
            Route::get('/roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/nouveau', [Admin\RoleController::class, 'create'])->name('roles.create');
            Route::post('/roles', [Admin\RoleController::class, 'store'])->name('roles.store');
            Route::get('/roles/{role}/modifier', [Admin\RoleController::class, 'edit'])->middleware('unlocked')->name('roles.edit');
            Route::put('/roles/{role}', [Admin\RoleController::class, 'update'])->middleware('unlocked')->name('roles.update');
            Route::delete('/roles/{role}', [Admin\RoleController::class, 'destroy'])->middleware('unlocked')->name('roles.destroy');
        });

        Route::middleware(['can:moderation.manage', 'unlocked'])->group(function () {
            Route::get('/avis', [Admin\ModerationController::class, 'reviews'])->name('reviews.index');
            Route::post('/avis/{review}/visibilite', [Admin\ModerationController::class, 'toggleReview'])->name('reviews.toggle');
            Route::post('/avis/{review}/publier', [Admin\ModerationController::class, 'approveReview'])->name('reviews.approve');
            Route::get('/signalements', [Admin\ModerationController::class, 'reports'])->name('reports.index');
            Route::post('/signalements/{report}', [Admin\ModerationController::class, 'handleReport'])->name('reports.handle');
        });

        Route::middleware(['can:marketplace.manage', 'unlocked'])->group(function () {
            Route::get('/pieces', [Admin\MarketplaceController::class, 'index'])->name('marketplace.index');
            Route::get('/observatoire-prix', [Admin\PriceWatchController::class, 'index'])->name('price-watch');
            Route::post('/pieces/{product}/masquer', [Admin\MarketplaceController::class, 'hide'])->name('products.hide');
            Route::post('/pieces/{product}/republier', [Admin\MarketplaceController::class, 'restore'])->name('products.restore');
            Route::post('/pieces/{product}/publier', [Admin\MarketplaceController::class, 'approve'])->name('products.approve');
            Route::post('/pieces/{product}/une', [Admin\MarketplaceController::class, 'toggleFeatured'])->name('products.featured');
            Route::post('/demandes/{partRequest}/cloturer', [Admin\MarketplaceController::class, 'closeRequest'])->name('part-requests.close');
        });

        Route::middleware(['can:subscriptions.manage', 'unlocked'])->group(function () {
            Route::get('/abonnements', [Admin\SubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::post('/abonnements', [Admin\SubscriptionController::class, 'store'])->name('subscriptions.store');
            Route::post('/abonnements/{subscription}/activer', [Admin\SubscriptionController::class, 'activate'])->name('subscriptions.activate');
            Route::post('/abonnements/{subscription}/annuler', [Admin\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
            Route::post('/abonnements/{subscription}/refuser', [Admin\SubscriptionController::class, 'reject'])->name('subscriptions.reject');
            Route::get('/abonnements/{subscription}/preuve', [Admin\SubscriptionController::class, 'proof'])->name('subscriptions.proof');
            Route::get('/offres', [Admin\PlanController::class, 'index'])->name('plans.index');
            Route::get('/offres/nouvelle', [Admin\PlanController::class, 'create'])->name('plans.create');
            Route::post('/offres', [Admin\PlanController::class, 'store'])->name('plans.store');
            Route::get('/offres/{plan}/modifier', [Admin\PlanController::class, 'edit'])->name('plans.edit');
            Route::put('/offres/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update');
            Route::post('/offres/{plan}/defaut', [Admin\PlanController::class, 'makeDefault'])->name('plans.default');
        });

        Route::middleware(['can:referentials.manage', 'unlocked'])->group(function () {
            Route::get('/services', [Admin\SettingsController::class, 'services'])->name('services.index');
            Route::post('/services', [Admin\SettingsController::class, 'storeService'])->name('services.store');
            Route::post('/services/{service}/statut', [Admin\SettingsController::class, 'toggleService'])->name('services.toggle');
            Route::post('/categories', [Admin\SettingsController::class, 'storeCategory'])->name('categories.store');
            Route::post('/pieces/categories', [Admin\MarketplaceController::class, 'storeCategory'])->name('part-categories.store');

            Route::get('/types', [Admin\ContentController::class, 'types'])->name('types.index');
            Route::post('/types', [Admin\ContentController::class, 'storeType'])->name('types.store');
            Route::put('/types/{type}', [Admin\ContentController::class, 'updateType'])->name('types.update');
            Route::put('/metiers/{type:slug}/modules', [Admin\TradeController::class, 'modules'])->name('trades.modules');

            // Formulaires des métiers : champs ajoutés par l'admin aux fiches pro
            Route::get('/formulaires', [Admin\FormController::class, 'index'])->name('forms.index');
            Route::post('/formulaires', [Admin\FormController::class, 'store'])->name('forms.store');
            Route::put('/formulaires/{field}', [Admin\FormController::class, 'update'])->name('forms.update');
            Route::delete('/formulaires/{field}', [Admin\FormController::class, 'destroy'])->name('forms.destroy');

            Route::get('/zones', [Admin\SettingsController::class, 'locations'])->name('locations.index');
            Route::post('/zones/villes', [Admin\SettingsController::class, 'storeCity'])->name('cities.store');
            Route::post('/zones/villes/{city}/statut', [Admin\SettingsController::class, 'toggleCity'])->name('cities.toggle');
            Route::post('/zones/villes/{city}/quartiers', [Admin\SettingsController::class, 'storeNeighborhood'])->name('neighborhoods.store');
        });

        Route::middleware(['can:content.manage', 'unlocked'])->group(function () {
            Route::get('/contenus', [Admin\ContentController::class, 'pages'])->name('pages.index');
            Route::get('/contenus/nouvelle', [Admin\ContentController::class, 'createPage'])->name('pages.create');
            Route::post('/contenus', [Admin\ContentController::class, 'storePage'])->name('pages.store');
            Route::get('/contenus/{page}/modifier', [Admin\ContentController::class, 'editPage'])->name('pages.edit');
            Route::put('/contenus/{page}', [Admin\ContentController::class, 'updatePage'])->name('pages.update');
            Route::delete('/contenus/{page}', [Admin\ContentController::class, 'destroyPage'])->name('pages.destroy');
        });

        // Réglages : la permission dépend du groupe (voir ConfigController::permissionFor)
        Route::post('/reglages/test-email', [Admin\ConfigController::class, 'testMail'])->middleware('can:settings.manage')->name('settings.test-mail');
        Route::get('/reglages/{group?}', [Admin\ConfigController::class, 'index'])->name('settings.index');
        Route::put('/reglages/{group}', [Admin\ConfigController::class, 'update'])->name('settings.update');

        Route::middleware('can:history.view')->group(function () {
            Route::get('/historique', [Admin\HistoryController::class, 'index'])->name('history.index');
            Route::get('/historique/export', [Admin\HistoryController::class, 'export'])->middleware('can:data.export')->name('history.export');
        });

        Route::middleware('can:backups.manage')->group(function () {
            Route::get('/sauvegardes', [Admin\BackupController::class, 'index'])->name('backups.index');
            Route::post('/sauvegardes', [Admin\BackupController::class, 'store'])->middleware('throttle:5,1')->name('backups.store');
            Route::get('/sauvegardes/{file}', [Admin\BackupController::class, 'download'])->name('backups.download');
            Route::delete('/sauvegardes/{file}', [Admin\BackupController::class, 'destroy'])->name('backups.destroy');
            Route::post('/sauvegardes/{file}/restaurer', [Admin\BackupController::class, 'restore'])->middleware('throttle:3,1')->name('backups.restore');
        });

        // Import / export : chaque jeu de données vérifie aussi sa permission métier (DataController)
        Route::get('/donnees', [Admin\DataController::class, 'index'])->name('data.index');
        Route::get('/donnees/export/{dataset}', [Admin\DataController::class, 'export'])->middleware('can:data.export')->name('data.export');
        Route::post('/donnees/import/{dataset}', [Admin\DataController::class, 'import'])->middleware(['can:data.import', 'throttle:10,1'])->name('data.import');
    });
});
