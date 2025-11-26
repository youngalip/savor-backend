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
// QR SCANNING & SESSION MANAGEMENT (Customer)
// ==========================================

// ✅ Main QR Scan Endpoint (FIXED: akan support format QR_A1_123)
Route::post('/scan-qr', [QRController::class, 'scanQR'])
    ->name('scan-qr');

// ✅ Session Management
Route::prefix('session')->name('session.')->group(function () {
    // Get session info by token
    Route::get('/{token}', [QRController::class, 'getSession'])
        ->name('get');
    
    // ✅ NEW: Validate if session still active (untuk auto-check di frontend)
    Route::post('/validate', [QRController::class, 'validateSession'])
        ->name('validate');
    
    // ✅ NEW: Extend session (untuk keep-alive mechanism)
    Route::post('/{token}/extend', [QRController::class, 'extendSession'])
        ->name('extend');
});

// ==========================================
// ✅ NEW: PUBLIC QR IMAGE ACCESS
// Untuk serve QR image dari storage
// ==========================================
Route::get('/qr-image/{filename}', function ($filename) {
    // Sanitize filename
    $filename = basename($filename);
    $path = storage_path('app/public/uploads/qr-codes/' . $filename);
    
    if (!file_exists($path)) {
        abort(404, 'QR code not found');
    }
    
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $mimeType = $extension === 'svg' ? 'image/svg+xml' : 'image/png';
    
    return response()->file($path, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000', // Cache 1 year
    ]);
})->name('qr.image');

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