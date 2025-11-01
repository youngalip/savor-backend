<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CashierController;

/*
|--------------------------------------------------------------------------
| Cashier Panel API Routes
|--------------------------------------------------------------------------
| Routes untuk panel kasir
| Prefix: /api/v1/cashier
*/

Route::prefix('cashier')->group(function () {
    
    // Get orders with filters
    Route::get('/orders', [CashierController::class, 'getOrders']);
    
    // Get single order detail
    Route::get('/orders/{id}', [CashierController::class, 'getOrder']);
    
    // Validate cash payment
    Route::patch('/orders/{id}/validate-payment', [CashierController::class, 'validatePayment']);
    
    // Mark order as completed
    Route::patch('/orders/{id}/complete', [CashierController::class, 'markCompleted']);
    
    // Reopen completed order (Undo complete) - Optional safety feature
    Route::patch('/orders/{id}/reopen', [CashierController::class, 'reopenOrder']);
    
    // Get statistics
    Route::get('/statistics', [CashierController::class, 'getStatistics']);
    
});