<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProxyController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\VipController;
use App\Http\Controllers\Api\ImageServerController;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Public comic proxy routes (no auth required)
Route::get('/latest', [ProxyController::class, 'proxy']);
Route::get('/promote', [ProxyController::class, 'proxy']);
Route::get('/album', [ProxyController::class, 'proxy']);
Route::get('/chapter', [ProxyController::class, 'proxy']);
Route::get('/forum', [ProxyController::class, 'proxy']);
Route::get('/hot_tags', [ProxyController::class, 'proxy']);
Route::get('/categories', [ProxyController::class, 'proxy']);
Route::get('/categories/filter', [ProxyController::class, 'proxy']);
Route::get('/serialization', [ProxyController::class, 'proxy']);
Route::get('/week/filter', [ProxyController::class, 'proxy']);

// VIP plans (public)
Route::get('/vip/plans', [VipController::class, 'plans']);

// Image servers (public - get list)
Route::get('/image-servers', [ImageServerController::class, 'index']);

// Catalog/Ranking APIs (public)
Route::get('/catalog/ranking', [\App\Http\Controllers\Api\CatalogController::class, 'getRanking']);
Route::get('/catalog/categories', [\App\Http\Controllers\Api\CatalogController::class, 'getCategories']);

// Random Comics API (public)
Route::get('/random-comics', [\App\Http\Controllers\Api\RandomComicsController::class, 'getRandomComics']);
Route::get('/comic/{id}', [\App\Http\Controllers\Api\RandomComicsController::class, 'getComicDetail']);

// Test decrypt endpoints (public for testing)
Route::prefix('test')->group(function () {
    Route::get('/decrypt/catalog', [\App\Http\Controllers\Api\TestDecryptController::class, 'testCatalog']);
    Route::post('/decrypt/raw', [\App\Http\Controllers\Api\TestDecryptController::class, 'testDecrypt']);
    Route::get('/decrypt/token', [\App\Http\Controllers\Api\TestDecryptController::class, 'testToken']);
    Route::post('/decrypt/fetch', [\App\Http\Controllers\Api\TestDecryptController::class, 'testFetch']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::put('/user/image-server', [ImageServerController::class, 'update']);

    // Search requires authentication
    Route::get('/search', [ProxyController::class, 'proxy']);

    // Collection routes
    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']);
    Route::post('/collections/toggle', [CollectionController::class, 'toggle']);
    Route::get('/collections/check/{comicId}', [CollectionController::class, 'check']);
    Route::delete('/collections/{comicId}', [CollectionController::class, 'destroy']);
    Route::get('/collections/stats', [CollectionController::class, 'stats']);

    // VIP order creation (requires auth)
    Route::post('/vip/create-order', [VipController::class, 'createOrder']);

    // Recharge history
    Route::get('/recharge-history', [\App\Http\Controllers\Api\RechargeHistoryController::class, 'index']);
});
