<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
| Base URL: /api/v1/auth
*/

Route::prefix('auth')->name('auth.')->group(function () {
    
    // ==========================================
    // PUBLIC ROUTES (No authentication required)
    // ==========================================

    Route::post('/login', [AuthController::class, 'login'])
        ->name('login');
     
    // ==========================================
    // PROTECTED ROUTES (JWT authentication required)
    // ==========================================
    
    Route::middleware(['jwt.auth'])->group(function () {
        
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('logout');
        
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->name('refresh');
        
        Route::get('/me', [AuthController::class, 'me'])
            ->name('me');
    });
});
