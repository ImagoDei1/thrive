<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ContentLikeController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\MediaMtxController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Public — no auth required
// ---------------------------------------------------------------------------
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

// Prices must be visible before sign-up (app paywall + in-app browser sign-up)
Route::get('plans', [SubscriptionController::class, 'plans']);

// ---------------------------------------------------------------------------
// MediaMTX server callbacks — not user auth. Secret travels as a query param
// baked into the URLs configured in mediamtx.yml (authHTTPAddress / runOnReady
// / runOnNotReady), verified by the 'mediamtx' middleware.
// ---------------------------------------------------------------------------
Route::middleware('mediamtx')->prefix('mediamtx')->group(function () {
    Route::post('auth',        [MediaMtxController::class, 'auth']);
    Route::post('published',   [MediaMtxController::class, 'published']);
    Route::post('unpublished', [MediaMtxController::class, 'unpublished']);
});

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
    Route::get('playlists/{id}',   [CatalogController::class, 'playlist']);

    // Likes
    Route::post('content-items/{id}/like',   [ContentLikeController::class, 'store']);
    Route::delete('content-items/{id}/like', [ContentLikeController::class, 'destroy']);

    // Subscriptions
    Route::get('subscription',              [SubscriptionController::class, 'status']);
    Route::get('payment-operators',          [SubscriptionController::class, 'operators']);
    Route::post('subscription/checkout',     [SubscriptionController::class, 'checkout'])->middleware('throttle:10,1');
    Route::get('subscription/checkout/{reference}', [SubscriptionController::class, 'checkoutStatus'])->middleware('throttle:60,1');

    // Watch-time heartbeat (pre-existing)
    Route::post('heartbeat', HeartbeatController::class);
});
