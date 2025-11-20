<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StationController;

/*
|--------------------------------------------------------------------------
| Station API Routes (Kitchen / Bar / Pastry)
|--------------------------------------------------------------------------
|
| Routes untuk mengelola pesanan di setiap station (Kitchen, Bar, Pastry)
| 
| Station Types: kitchen, bar, pastry
| 
| 🔒 Protected: JWT Auth + Role (Kitchen, Bar, Pastry, Owner)
*/

Route::middleware(['jwt.auth', 'role:kitchen,bar,pastry'])->group(function () {
    
    // Get orders berdasarkan station type (dikelompokkan per meja)
    Route::get('/stations/{station_type}/orders', [StationController::class, 'getOrdersByStation'])
        ->where('station_type', 'kitchen|bar|pastry');

    // Update status single item
    Route::patch('/stations/items/{id}/status', [StationController::class, 'updateItemStatus']);

    // Batch update status multiple items
    Route::post('/stations/items/batch-update-status', [StationController::class, 'batchUpdateStatus']);

    // Get menu list untuk station tertentu
    Route::get('/stations/{station_type}/menus', [StationController::class, 'getMenusByStation'])
        ->where('station_type', 'kitchen|bar|pastry');

    // Update stock menu
    Route::patch('/stations/{station_type}/menus/{id}/stock', [StationController::class, 'updateMenuStock'])
        ->where('station_type', 'kitchen|bar|pastry');

    // Get dashboard statistics
    Route::get('/stations/{station_type}/stats', [StationController::class, 'getStationStats'])
        ->where('station_type', 'kitchen|bar|pastry');

    /*
    |--------------------------------------------------------------------------
    | Alias Routes untuk backward compatibility
    |--------------------------------------------------------------------------
    */

    // Kitchen specific routes (alias)
    Route::prefix('kitchen')->group(function () {
        Route::get('/orders', function () {
            return app(StationController::class)->getOrdersByStation('kitchen', request());
        });
        Route::get('/menus', function () {
            return app(StationController::class)->getMenusByStation('kitchen');
        });
        Route::get('/stats', function () {
            return app(StationController::class)->getStationStats('kitchen', request());
        });
    });

    // Bar specific routes (alias)
    Route::prefix('bar')->group(function () {
        Route::get('/orders', function () {
            return app(StationController::class)->getOrdersByStation('bar', request());
        });
        Route::get('/menus', function () {
            return app(StationController::class)->getMenusByStation('bar');
        });
        Route::get('/stats', function () {
            return app(StationController::class)->getStationStats('bar', request());
        });
    });

    // Pastry specific routes (alias)
    Route::prefix('pastry')->group(function () {
        Route::get('/orders', function () {
            return app(StationController::class)->getOrdersByStation('pastry', request());
        });
        Route::get('/menus', function () {
            return app(StationController::class)->getMenusByStation('pastry');
        });
        Route::get('/stats', function () {
            return app(StationController::class)->getStationStats('pastry', request());
        });
    });
    
});