<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KitchenController;

/*
|--------------------------------------------------------------------------
| Kitchen/Bar/Pastry API Routes
|--------------------------------------------------------------------------
| Routes untuk dapur panel - kitchen, bar, dan pastry
| Requires authentication
*/

// Authentication
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth management
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    
    // Kitchen operations
    Route::prefix('kitchen')->name('kitchen.')->group(function () {
        // Get queue (pending orders by category)
        Route::get('/queue', [KitchenController::class, 'getQueue'])->name('queue');
        
        // Mark item as done
        Route::post('/items/{itemId}/done', [KitchenController::class, 'markItemDone'])->name('items.done');
        
        // Dashboard stats
        Route::get('/dashboard', [KitchenController::class, 'getDashboard'])->name('dashboard');
        
        // Completed items history
        Route::get('/completed', [KitchenController::class, 'getCompletedItems'])->name('completed');
    });
});