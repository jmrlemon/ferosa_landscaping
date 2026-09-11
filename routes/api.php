<?php

use App\Http\Controllers\ArController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\MobileController;
use Illuminate\Support\Facades\Route;

// The Android app signs in through the embedded web experience. Reuse that
// authenticated Laravel session for native AR requests instead of exposing a
// bearer token in a deep link. The Android CookieJar forwards the same session
// and XSRF cookies used by the WebView.
Route::middleware(['web', 'auth'])->prefix('ar')->group(function () {
    Route::get('/products', [ArController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/products/{product}/model', [ArController::class, 'downloadModel'])->name('api.ar.model')->middleware('throttle:10,1');
    Route::get('/products/{product}/model-info', [ArController::class, 'modelInfo'])->middleware('throttle:60,1');
    Route::post('/cart/add', [ArController::class, 'addToCart'])->middleware('throttle:30,1');
});

Route::middleware(['web', 'auth'])->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index'])->middleware('throttle:120,1');
    Route::post('/items', [CartController::class, 'store'])->middleware('throttle:60,1');
    Route::put('/items/{product}', [CartController::class, 'update'])->middleware('throttle:60,1');
    Route::delete('/items/{product}', [CartController::class, 'destroy'])->middleware('throttle:60,1');
    Route::post('/sync', [CartController::class, 'sync'])->middleware('throttle:20,1');
});

// Native shell endpoints. `summary` is polled by the app's background worker as
// well as on every resume, hence the higher throttle; the rate card changes
// about as often as prices do, so the app caches it aggressively.
Route::middleware(['web', 'auth'])->prefix('mobile')->group(function () {
    Route::get('/summary', [MobileController::class, 'summary'])->middleware('throttle:120,1');
    Route::get('/estimator-rates', [MobileController::class, 'estimatorRates'])->middleware('throttle:60,1');
});
