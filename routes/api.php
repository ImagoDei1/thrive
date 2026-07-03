<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Public — no auth required
// ---------------------------------------------------------------------------
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

// ---------------------------------------------------------------------------
// Authenticated — Bearer api_token required
// ---------------------------------------------------------------------------
Route::middleware('auth.api')->group(function () {

    // Auth
    Route::post('logout',          [AuthController::class, 'logout']);
    Route::get('me',               [AuthController::class, 'me']);

    // Catalog
    Route::get('categories',       [CatalogController::class, 'categories']);
    Route::get('channels',         [CatalogController::class, 'channels']);
    Route::get('channels/{id}',    [CatalogController::class, 'channel']);

    // Plans & subscriptions
    Route::get('plans',                      [SubscriptionController::class, 'plans']);
    Route::get('subscription',               [SubscriptionController::class, 'status']);
    Route::post('subscription/checkout',     [SubscriptionController::class, 'checkout']);

    // Watch-time heartbeat (pre-existing)
    Route::post('heartbeat', HeartbeatController::class);
});
