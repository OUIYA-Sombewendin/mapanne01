<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use Illuminate\Support\Facades\Route;

/*
 * API REST v1 — pour la future application Flutter (mêmes comptes, mêmes données).
 */
Route::prefix('v1')->name('api.')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
    });

    Route::middleware('throttle:60,1')->group(function () {
        Route::get('references', [CatalogController::class, 'references']);
        Route::get('providers', [CatalogController::class, 'providers']);
        Route::get('providers/{provider}', [CatalogController::class, 'provider']);
        Route::get('products', [CatalogController::class, 'products']);
        Route::get('products/{product}', [CatalogController::class, 'product']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });
});
