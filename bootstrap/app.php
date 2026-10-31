<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureEmailIsVerifiedIfRequired;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUnlocked;
use App\Http\Middleware\MaintenanceMode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'verified.if' => EnsureEmailIsVerifiedIfRequired::class,
            'feature' => EnsureFeatureEnabled::class,
            'plan' => EnsurePlanFeature::class,
            'unlocked' => EnsureUnlocked::class,
            'shop' => \App\Http\Middleware\EnsureShopAccess::class,
            'admin.idle' => \App\Http\Middleware\AdminIdleTimeout::class,
            'not-impersonating' => \App\Http\Middleware\EnsureNotImpersonating::class,
        ]);
        // Côté API, la suspension révoque les jetons (voir Admin\UserController).
        $middleware->web(append: [EnsureAccountIsActive::class, MaintenanceMode::class, \App\Http\Middleware\SecurityHeaders::class]);
        $middleware->api(append: [MaintenanceMode::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn ($request) => $request->user()->homeRoute());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
