<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MenuAdminController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\ReportController;

/*
|--------------------------------------------------------------------------
| Owner/Manager Dashboard Routes
|--------------------------------------------------------------------------
|
| These routes handle Owner and Manager dashboard functionality:
| - Analytics & Metrics
| - Staff Management (CRUD)
| - Menu Management (CRUD)
| - Table Management (CRUD + QR)
| - Sales Reports & Analytics
|
| Middleware: jwt.auth, owner.access (need to be implemented)
*/

Route::prefix('owner')->name('owner.')->group(function () {
    
    // ==========================================
    // DASHBOARD & ANALYTICS
    // ==========================================
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    
    
    // ==========================================
    // USER/STAFF MANAGEMENT
    // ==========================================
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/{id}', [UserController::class, 'show'])->name('show');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
        
        // Password management
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])
            ->name('reset-password');
    });
    
    
    // ==========================================
    // MENU MANAGEMENT
    // ==========================================
    Route::prefix('menus')->name('menus.')->group(function () {
        Route::get('/', [MenuAdminController::class, 'index'])->name('index');
        Route::get('/{id}', [MenuAdminController::class, 'show'])->name('show');
        Route::post('/', [MenuAdminController::class, 'store'])->name('store');
        Route::put('/{id}', [MenuAdminController::class, 'update'])->name('update');
        Route::delete('/{id}', [MenuAdminController::class, 'destroy'])->name('destroy');
        
        // Image upload
        Route::post('/{id}/image', [MenuAdminController::class, 'uploadImage'])
            ->name('upload-image');
        
        // Utilities
        Route::get('/subcategories/list', [MenuAdminController::class, 'getSubcategories'])
            ->name('subcategories');
    });
    
    
    // ==========================================
    // TABLE MANAGEMENT
    // ==========================================
    Route::prefix('tables')->name('tables.')->group(function () {
        Route::get('/', [TableController::class, 'index'])->name('index');
        Route::get('/{id}', [TableController::class, 'show'])->name('show');
        Route::post('/', [TableController::class, 'store'])->name('store');
        Route::put('/{id}', [TableController::class, 'update'])->name('update');
        Route::delete('/{id}', [TableController::class, 'destroy'])->name('destroy');
        
        // QR Code generation
        Route::post('/{id}/generate-qr', [TableController::class, 'generateQRCode'])
            ->name('generate-qr');
        Route::post('/generate-qr-bulk', [TableController::class, 'bulkGenerateQR'])
            ->name('generate-qr-bulk');
    });
    
    
    // ==========================================
    // SALES REPORTS & ANALYTICS
    // ==========================================
    Route::prefix('reports')->name('reports.')->group(function () {
        // Main reports
        Route::get('/overview', [ReportController::class, 'overview'])
            ->name('overview');
        Route::get('/revenue', [ReportController::class, 'revenue'])
            ->name('revenue');
        Route::get('/revenue/aggregated', [ReportController::class, 'revenueAggregated']);
        Route::get('/menu-performance', [ReportController::class, 'menuPerformance'])
            ->name('menu-performance');
        Route::get('/peak-hours', [ReportController::class, 'peakHours'])
            ->name('peak-hours');

        // Export
        Route::get('/export', [ReportController::class, 'export'])
            ->name('export');
    });
    
});
