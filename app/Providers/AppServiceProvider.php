<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\User;
use App\Support\MailConfigurator;
use App\Support\Permissions;
use App\Support\Theme;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Serveur d'emails configurable depuis l'admin.
        MailConfigurator::apply();

        // Permissions d'administration : chaque clé de App\Support\Permissions est une capacité du Gate
        // (->middleware('can:providers.manage'), @can('providers.manage')), accordée selon le rôle de l'admin.
        Gate::before(function (User $user, string $ability) {
            return Permissions::exists($ability) ? $user->hasPermission($ability) : null;
        });

        // Couleurs choisies dans Admin › Apparence, injectées dans toutes les mises en page.
        View::composer(['components.layouts.app', 'components.layouts.admin', 'components.layouts.pro', 'pro.invoices.print'], function ($view) {
            $view->with('themeCss', Theme::css());
        });

        // Pages de contenu (CGU, confidentialité…) listées dans le pied de page.
        View::composer('components.layouts.app', function ($view) {
            $view->with('footerPages', Page::footer());
        });

        // Le lien de vérification d'email et de réinitialisation pointent vers nos routes en français.
        ResetPassword::createUrlUsing(fn ($user, string $token) => route('password.reset', ['token' => $token, 'email' => $user->email]));
    }
}
