<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Main Router
|--------------------------------------------------------------------------
| Savor QR Order System API
| Version: 1.0.0
*/

Route::prefix('v1')->group(function () {
    
    // ==========================================
    // SETTINGS
    // ==========================================
    require __DIR__ . '/api/settings.php';
    
    // ==========================================
    // CUSTOMER ROUTES (Public)
    // ==========================================
    require __DIR__ . '/api/customer.php';
    
    // ==========================================
    // STAFF ROUTES (Protected)
    // ==========================================
    
    // Kitchen/Bar/Pastry Panel
    Route::prefix('staff')->name('staff.')->group(function () {
        require __DIR__ . '/api/kitchen.php';
    });
    
    // Cashier Panel
    require __DIR__ . '/api/cashier.php';
    
    // ==========================================
    // OWNER & ADMIN ROUTES
    // ==========================================
    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/owner.php';
    
    // ==========================================
    // TESTING ROUTES (Disable in production!)
    // ==========================================
    if (config('app.env') !== 'production') {
        require __DIR__ . '/api/testing.php';
    }
    
    // ==========================================
    // HEALTH CHECK & API INFO
    // ==========================================
    Route::get('/health', function () {
        return response()->json([
            'status' => 'OK',
            'service' => 'Savor QR Order System API',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
            'environment' => config('app.env'),
            'database' => [
                'status' => 'Connected',
                'tables_count' => \App\Models\Table::count(),
                'menus_count' => \App\Models\Menu::count(),
                'categories_count' => \App\Models\Category::count(),
                'orders_today' => \App\Models\Order::whereDate('created_at', today())->count()
            ]
        ]);
    })->name('health');
});