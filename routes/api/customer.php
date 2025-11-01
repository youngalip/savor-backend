<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QRController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| Customer API Routes
|--------------------------------------------------------------------------
| Routes untuk customer flow - QR scan, menu browsing, ordering, payment
*/

// ==========================================
// QR SCANNING & SESSION MANAGEMENT
// ==========================================
Route::post('/scan-qr', [QRController::class, 'scanQR'])->name('scan-qr');
Route::get('/session/{token}', [QRController::class, 'getSession'])->name('session.show');

Route::prefix('session')->name('session.')->group(function () {
    Route::get('/{token}', [QRController::class, 'getSession'])->name('get');
    Route::post('/validate', [QRController::class, 'validateSession'])->name('validate');
    Route::post('/extend/{token}', [QRController::class, 'extendSession'])->name('extend');
});

// ==========================================
// MENU & CATEGORY BROWSING
// ==========================================
Route::prefix('menus')->name('menus.')->group(function () {
    Route::get('/', [MenuController::class, 'index'])->name('index');
    Route::get('/{id}', [MenuController::class, 'show'])->name('show');
});

Route::get('/categories', [MenuController::class, 'categories'])->name('categories.index');

// ==========================================
// ORDER MANAGEMENT
// ==========================================
Route::prefix('orders')->name('orders.')->group(function () {
    // Create order
    Route::post('/', [OrderController::class, 'store'])->name('store');
    
    // Calculate preview (before checkout)
    Route::post('/calculate', [OrderController::class, 'calculatePreview'])->name('calculate');
    
    // Get order detail
    Route::get('/{uuid}', [OrderController::class, 'show'])->name('show');
    
    // Order history
    Route::get('/history/device', [OrderController::class, 'deviceHistory'])->name('history.device');
    Route::get('/history/{sessionToken}', [OrderController::class, 'history'])->name('history.session');
});

// ==========================================
// PAYMENT PROCESSING
// ==========================================
Route::prefix('payment')->name('payment.')->group(function () {
    Route::post('/process', [PaymentController::class, 'process'])->name('process');
    Route::post('/callback', [PaymentController::class, 'callback'])->name('callback');
    Route::get('/finish', [PaymentController::class, 'finish'])->name('finish');
});