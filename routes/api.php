<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QRController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes - Savor QR Order System
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    
    // ==========================================
    // QR SCANNING & SESSION MANAGEMENT
    // ==========================================
    Route::post('/scan-qr', [QRController::class, 'scanQR']);
    Route::get('/session/{token}', [QRController::class, 'getSession']);
    
    // ==========================================
    // MENU & CATEGORY MANAGEMENT
    // ==========================================
    Route::get('/categories', [MenuController::class, 'categories']);
    Route::get('/menus', [MenuController::class, 'index']);
    Route::get('/menus/{id}', [MenuController::class, 'show']);
    
    // ==========================================
    // ORDER MANAGEMENT
    // ==========================================
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{uuid}', [OrderController::class, 'show']);
    
    // ==========================================
    // PAYMENT PROCESSING
    // ==========================================
    Route::post('/payment/process', [PaymentController::class, 'process']);
    Route::post('/payment/callback', [PaymentController::class, 'callback']);
    Route::get('/payment/finish', [PaymentController::class, 'finish']);
    
    // ==========================================
    // DEVELOPMENT/TESTING ENDPOINTS
    // ==========================================
    Route::prefix('test')->group(function () {
        // Quick data check endpoints
        Route::get('/tables', function() {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Table::all()
            ]);
        });
        
        Route::get('/customers', function() {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Customer::latest()->take(10)->get()
            ]);
        });
        
        Route::get('/orders', function() {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Order::with(['items.menu', 'table', 'customer'])
                                          ->latest()
                                          ->take(5)
                                          ->get()
            ]);
        });
        
        // Reset table status untuk testing
        Route::post('/reset-tables', function() {
            \App\Models\Table::query()->update(['status' => 'Free']);
            return response()->json([
                'success' => true,
                'message' => 'Semua table status direset ke Free'
            ]);
        });
        
        // Simulate payment success untuk testing
        Route::post('/simulate-payment/{orderUuid}', function($orderUuid) {
            $order = \App\Models\Order::where('order_uuid', $orderUuid)->first();
            
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }
            
            $order->update([
                'payment_status' => 'Paid',
                'payment_reference' => 'SIM_' . time(),
                'paid_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Payment simulated successfully',
                'data' => $order->fresh()
            ]);
        });
    });
});

/*
|--------------------------------------------------------------------------
| HEALTH CHECK & API INFO
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'service' => 'Savor QR Order System API',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
        'database' => [
            'status' => 'Connected',
            'tables_count' => \App\Models\Table::count(),
            'menus_count' => \App\Models\Menu::count(),
            'categories_count' => \App\Models\Category::count()
        ]
    ]);
});

Route::get('/api-docs', function () {
    return response()->json([
        'api_name' => 'Savor QR Order System API',
        'version' => '1.0.0',
        'base_url' => url('/api/v1'),
        'endpoints' => [
            'QR & Session' => [
                'POST /scan-qr' => 'Scan QR code untuk mulai session',
                'GET /session/{token}' => 'Get session info berdasarkan token'
            ],
            'Menu & Category' => [
                'GET /categories' => 'Get semua kategori aktif',
                'GET /menus' => 'Get semua menu (dengan filter)',
                'GET /menus/{id}' => 'Get detail menu'
            ],
            'Order' => [
                'POST /orders' => 'Create order baru',
                'GET /orders/{uuid}' => 'Get detail order'
            ],
            'Payment' => [
                'POST /payment/process' => 'Process payment',
                'POST /payment/callback' => 'Payment gateway callback'
            ],
            'Testing' => [
                'GET /test/tables' => 'Get semua table',
                'GET /test/customers' => 'Get latest customers',
                'GET /test/orders' => 'Get latest orders',
                'POST /test/reset-tables' => 'Reset table status',
                'POST /test/simulate-payment/{uuid}' => 'Simulate payment success'
            ]
        ],
        'sample_flow' => [
            '1. Scan QR' => 'POST /scan-qr',
            '2. Browse Menu' => 'GET /categories, GET /menus',
            '3. Create Order' => 'POST /orders',
            '4. Process Payment' => 'POST /payment/process',
            '5. Check Order' => 'GET /orders/{uuid}'
        ]
    ]);
});