<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QRController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KitchenController;

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
    // Order History by Session Token
    Route::get('/orders/history/{sessionToken}', function($sessionToken) {
        try {
            $customer = \App\Models\Customer::where('session_token', $sessionToken)->first();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found'
                ], 404);
            }
            
            $orders = \App\Models\Order::with(['items.menu', 'table'])
                           ->where('customer_id', $customer->id)
                           ->where('created_at', '>=', now()->subHours(4)) // Last 4 hours
                           ->orderBy('created_at', 'desc')
                           ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'customer_uuid' => $customer->uuid,
                    'total_orders' => $orders->count(),
                    'total_spent' => $orders->where('payment_status', 'Paid')->sum('total_amount'),
                    'orders' => $orders
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get order history: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // ==========================================
    // PAYMENT PROCESSING
    // ==========================================
    Route::post('/payment/process', [PaymentController::class, 'process']);
    Route::post('/payment/callback', [PaymentController::class, 'callback']);
    Route::get('/payment/finish', [PaymentController::class, 'finish']);
    
    // ==========================================
    // SESSION MANAGEMENT
    // ==========================================
    Route::prefix('session')->group(function () {
        Route::get('/{token}', [QRController::class, 'getSession']);
        Route::post('/validate', [QRController::class, 'validateSession']);
        Route::post('/extend/{token}', [QRController::class, 'extendSession']);
    });

    // ==========================================
    // ADMIN ENDPOINTS
    // ==========================================
    Route::prefix('admin')->group(function () {
        
        // Force Reset Table
        Route::post('/force-reset-table/{tableId}', function($tableId) {
            $table = \App\Models\Table::find($tableId);
            
            if (!$table) {
                return response()->json(['success' => false, 'message' => 'Table not found'], 404);
            }
            
            $table->update(['status' => 'Free']);
            
            \App\Models\Order::where('table_id', $tableId)
                            ->where('payment_status', 'Pending')
                            ->update(['payment_status' => 'Cancelled']);
            
            return response()->json([
                'success' => true,
                'message' => "Table {$table->table_number} berhasil direset",
                'data' => $table->fresh()
            ]);
        });

        // Session Management
        Route::post('/cleanup-sessions', function() {
            $sessionService = new \App\Services\SessionService();
            return response()->json($sessionService->cleanupExpiredSessions());
        });
        
        Route::get('/session-stats', function() {
            return response()->json([
                'success' => true,
                'data' => [
                    'active_sessions' => \App\Models\Order::where('session_expires_at', '>', now())->count(),
                    'occupied_tables' => \App\Models\Table::where('status', 'Occupied')->count(),
                    'pending_orders' => \App\Models\Order::where('payment_status', 'Pending')->count(),
                    'paid_orders_today' => \App\Models\Order::where('payment_status', 'Paid')
                                                        ->whereDate('created_at', today())
                                                        ->count()
                ]
            ]);
        });

        // ==========================================
        // QR CODE MANAGEMENT
        // ==========================================
        Route::prefix('qr')->group(function () {
            
            // Generate QR Code (SVG)
            Route::get('/generate/{tableId}', function($tableId) {
                try {
                    $table = \App\Models\Table::find($tableId);
                    
                    if (!$table) {
                        return response()->json(['success' => false, 'message' => 'Table not found'], 404);
                    }

                    $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                                ->size(300)
                                ->errorCorrection('H')
                                ->generate($table->qr_code);

                    return response($qrCode)->header('Content-Type', 'image/svg+xml');
                    
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to generate QR code: ' . $e->getMessage()
                    ], 500);
                }
            });
            
            // Custom Branded QR Code
            Route::get('/custom/{tableId}', function($tableId) {
                try {
                    $table = \App\Models\Table::find($tableId);
                    
                    if (!$table) {
                        return response('Table not found', 404);
                    }

                    $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                                ->size(300)
                                ->errorCorrection('H')
                                ->margin(0)
                                ->generate($table->qr_code);

                    $customSvg = '
                    <svg width="400" height="500" xmlns="http://www.w3.org/2000/svg">
                        <rect width="400" height="500" fill="#ffffff" stroke="#e74c3c" stroke-width="3" rx="20"/>
                        <rect x="0" y="0" width="400" height="100" fill="#e74c3c" rx="20"/>
                        <rect x="0" y="80" width="400" height="20" fill="#e74c3c"/>
                        <text x="200" y="40" text-anchor="middle" fill="white" font-family="Arial Black, sans-serif" font-size="28" font-weight="bold">🍽️ SAVOR</text>
                        <text x="200" y="70" text-anchor="middle" fill="white" font-family="Arial, sans-serif" font-size="16">Scan untuk Memesan</text>
                        <rect x="50" y="130" width="300" height="300" fill="white" stroke="#ddd" stroke-width="1" rx="10"/>
                        <g transform="translate(50, 130)">
                            ' . preg_replace('/<\?xml[^>]+\?>/', '', $qrCode) . '
                        </g>
                        <rect x="50" y="450" width="300" height="40" fill="#2c3e50" rx="10"/>
                        <text x="200" y="475" text-anchor="middle" fill="white" font-family="Arial Black, sans-serif" font-size="20" font-weight="bold">MEJA ' . $table->table_number . '</text>
                        <text x="200" y="400" text-anchor="middle" fill="#666" font-family="Arial, sans-serif" font-size="12">Arahkan kamera ke QR Code</text>
                        <text x="200" y="415" text-anchor="middle" fill="#666" font-family="Arial, sans-serif" font-size="12">untuk membuka menu digital</text>
                    </svg>';

                    return response($customSvg)->header('Content-Type', 'image/svg+xml');
                    
                } catch (\Exception $e) {
                    return response('Error: ' . $e->getMessage(), 500);
                }
            });
            
            // Print Single Table
            Route::get('/print/{tableId}', function($tableId) {
                try {
                    $table = \App\Models\Table::find($tableId);
                    
                    if (!$table) {
                        return response('Table not found', 404);
                    }

                    $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                                ->size(250)
                                ->errorCorrection('H')
                                ->margin(0)
                                ->generate($table->qr_code);

                    $qrCodeSvg = preg_replace('/<\?xml[^>]+\?>/', '', $qrCodeSvg);

                    $html = '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>QR Code - Meja ' . $table->table_number . '</title>
                        <style>
                            @media print {
                                @page { margin: 0; size: A5; }
                                body { margin: 0; }
                                .no-print { display: none; }
                            }
                            body { 
                                font-family: Arial, sans-serif; 
                                margin: 0; 
                                padding: 20px;
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                justify-content: center;
                                min-height: 100vh;
                                background: white;
                            }
                            .qr-container {
                                text-align: center;
                                border: 3px solid #e74c3c;
                                border-radius: 20px;
                                padding: 30px;
                                background: white;
                                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                                max-width: 350px;
                            }
                            .header {
                                background: #e74c3c;
                                color: white;
                                padding: 20px;
                                margin: -30px -30px 20px -30px;
                                border-radius: 17px 17px 0 0;
                            }
                            .logo { font-size: 32px; font-weight: bold; margin-bottom: 5px; }
                            .subtitle { font-size: 16px; opacity: 0.9; }
                            .qr-code {
                                margin: 20px 0;
                                width: 250px;
                                height: 250px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            }
                            .qr-code svg { width: 250px; height: 250px; }
                            .table-number {
                                background: #2c3e50;
                                color: white;
                                padding: 15px;
                                margin: 20px -30px -30px -30px;
                                border-radius: 0 0 17px 17px;
                                font-size: 24px;
                                font-weight: bold;
                            }
                            .instructions {
                                color: #666;
                                font-size: 14px;
                                margin: 15px 0;
                                line-height: 1.4;
                            }
                            .print-button {
                                background: #27ae60;
                                color: white;
                                padding: 10px 20px;
                                border: none;
                                border-radius: 5px;
                                font-size: 16px;
                                cursor: pointer;
                                margin-top: 20px;
                            }
                            .print-button:hover { background: #229954; }
                        </style>
                    </head>
                    <body>
                        <div class="qr-container">
                            <div class="header">
                                <div class="logo">🍽️ SAVOR</div>
                                <div class="subtitle">Scan untuk Memesan</div>
                            </div>
                            <div class="instructions">
                                Arahkan kamera smartphone Anda<br>
                                ke QR Code di bawah ini
                            </div>
                            <div class="qr-code">' . $qrCodeSvg . '</div>
                            <div class="table-number">MEJA ' . $table->table_number . '</div>
                        </div>
                        <button class="print-button no-print" onclick="window.print()">
                            🖨️ Print QR Code
                        </button>
                    </body>
                    </html>';

                    return response($html);
                    
                } catch (\Exception $e) {
                    return response('Error: ' . $e->getMessage(), 500);
                }
            });

            // Print All Tables (Standard Layout)
            Route::get('/print-all', function() {
                try {
                    $tables = \App\Models\Table::orderBy('table_number')->get();
                    
                    if ($tables->isEmpty()) {
                        return response('No tables found', 404);
                    }

                    $html = '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>All QR Codes - Savor Restaurant</title>
                        <style>
                            @media print {
                                @page { margin: 10mm; size: A4; }
                                body { margin: 0; }
                                .no-print { display: none; }
                                .page-break { page-break-before: always; }
                            }
                            body { 
                                font-family: Arial, sans-serif; 
                                margin: 0; 
                                padding: 20px;
                                background: white;
                            }
                            .header {
                                text-align: center;
                                margin-bottom: 30px;
                                padding: 20px;
                                background: #e74c3c;
                                color: white;
                                border-radius: 10px;
                            }
                            .header h1 { margin: 0; font-size: 28px; }
                            .header p { margin: 10px 0 0 0; opacity: 0.9; }
                            .qr-grid {
                                display: grid;
                                grid-template-columns: repeat(2, 1fr);
                                gap: 30px;
                                margin-bottom: 40px;
                            }
                            .qr-item {
                                border: 2px solid #e74c3c;
                                border-radius: 15px;
                                padding: 20px;
                                text-align: center;
                                background: white;
                                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                            }
                            .qr-header {
                                background: #e74c3c;
                                color: white;
                                padding: 15px;
                                margin: -20px -20px 15px -20px;
                                border-radius: 13px 13px 0 0;
                                font-weight: bold;
                                font-size: 18px;
                            }
                            .qr-code {
                                margin: 15px 0;
                                display: flex;
                                justify-content: center;
                                align-items: center;
                            }
                            .qr-code svg { width: 150px; height: 150px; }
                            .table-number {
                                background: #2c3e50;
                                color: white;
                                padding: 10px;
                                margin: 15px -20px -20px -20px;
                                border-radius: 0 0 13px 13px;
                                font-size: 16px;
                                font-weight: bold;
                            }
                            .instructions {
                                color: #666;
                                font-size: 11px;
                                margin: 10px 0;
                                line-height: 1.2;
                            }
                            .print-controls {
                                text-align: center;
                                margin: 30px 0;
                                padding: 20px;
                                background: #f8f9fa;
                                border-radius: 10px;
                            }
                            .print-button {
                                background: #27ae60;
                                color: white;
                                padding: 12px 25px;
                                border: none;
                                border-radius: 5px;
                                font-size: 16px;
                                cursor: pointer;
                                margin: 0 10px;
                            }
                            .print-button:hover { background: #229954; }
                            .stats {
                                color: #666;
                                margin-top: 15px;
                                font-size: 14px;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="header no-print">
                            <h1>🍽️ SAVOR QR Codes</h1>
                            <p>Print semua QR codes untuk ' . $tables->count() . ' meja</p>
                        </div>
                        
                        <div class="print-controls no-print">
                            <button class="print-button" onclick="window.print()">
                                🖨️ Print All QR Codes
                            </button>
                            <button class="print-button" onclick="location.href=\'/api/v1/admin/qr-manager\'" style="background: #3498db;">
                                ← Back to Manager
                            </button>
                            <div class="stats">
                                Total: ' . $tables->count() . ' meja | Ready untuk print
                            </div>
                        </div>';

                    foreach ($tables as $index => $table) {
                        $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                                    ->size(150)
                                    ->errorCorrection('H')
                                    ->margin(0)
                                    ->generate($table->qr_code);
                        
                        $qrCodeSvg = preg_replace('/<\?xml[^>]+\?>/', '', $qrCodeSvg);
                        
                        if ($index % 4 == 0) {
                            $html .= '<div class="qr-grid">';
                        }
                        
                        $html .= '
                            <div class="qr-item">
                                <div class="qr-header">🍽️ SAVOR</div>
                                <div class="instructions">Scan untuk Memesan</div>
                                <div class="qr-code">' . $qrCodeSvg . '</div>
                                <div class="table-number">MEJA ' . $table->table_number . '</div>
                            </div>';
                        
                        if (($index + 1) % 4 == 0 || $index == $tables->count() - 1) {
                            $html .= '</div>';
                            
                            if ($index != $tables->count() - 1) {
                                $html .= '<div class="page-break"></div>';
                            }
                        }
                    }

                    $html .= '
                        <div class="no-print" style="text-align: center; margin-top: 40px; color: #666; font-size: 12px;">
                            <p>Generated on ' . now()->format('d M Y, H:i') . ' | Savor QR Order System</p>
                        </div>
                    </body>
                    </html>';

                    return response($html);
                    
                } catch (\Exception $e) {
                    return response('Error: ' . $e->getMessage(), 500);
                }
            });

            // Print All Compact (Landscape A4)
            Route::get('/print-all-compact', function() {
                try {
                    $tables = \App\Models\Table::orderBy('table_number')->get();
                    
                    $html = '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>All QR Codes Compact - Savor</title>
                        <style>
                            @media print {
                                @page { margin: 10mm; size: A4 landscape; }
                                body { margin: 0; }
                                .no-print { display: none; }
                            }
                            body { 
                                font-family: Arial, sans-serif; 
                                margin: 0; 
                                padding: 15px;
                                background: white;
                            }
                            .header {
                                text-align: center;
                                margin-bottom: 20px;
                                font-size: 18px;
                                font-weight: bold;
                                color: #e74c3c;
                            }
                            .qr-grid {
                                display: grid;
                                grid-template-columns: repeat(4, 1fr);
                                gap: 15px;
                            }
                            .qr-item {
                                border: 1px solid #ddd;
                                border-radius: 8px;
                                padding: 10px;
                                text-align: center;
                                background: white;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                            }
                            .qr-header {
                                background: #e74c3c;
                                color: white;
                                padding: 8px;
                                margin: -10px -10px 8px -10px;
                                border-radius: 7px 7px 0 0;
                                font-weight: bold;
                                font-size: 12px;
                            }
                            .qr-code svg { width: 100px; height: 100px; }
                            .table-number {
                                background: #2c3e50;
                                color: white;
                                padding: 5px;
                                margin: 8px -10px -10px -10px;
                                border-radius: 0 0 7px 7px;
                                font-size: 14px;
                                font-weight: bold;
                            }
                            .print-button {
                                background: #27ae60;
                                color: white;
                                padding: 10px 20px;
                                border: none;
                                border-radius: 5px;
                                margin: 10px;
                                cursor: pointer;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="header no-print">
                            🍽️ SAVOR QR Codes - Compact Layout (' . $tables->count() . ' meja)
                            <button class="print-button" onclick="window.print()">🖨️ Print All</button>
                        </div>
                        
                        <div class="qr-grid">';

                    foreach ($tables as $table) {
                        $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                                    ->size(100)
                                    ->errorCorrection('H')
                                    ->margin(0)
                                    ->generate($table->qr_code);
                        
                        $qrCodeSvg = preg_replace('/<\?xml[^>]+\?>/', '', $qrCodeSvg);
                        
                        $html .= '
                            <div class="qr-item">
                                <div class="qr-header">SAVOR</div>
                                <div class="qr-code">' . $qrCodeSvg . '</div>
                                <div class="table-number">MEJA ' . $table->table_number . '</div>
                            </div>';
                    }

                    $html .= '
                        </div>
                    </body>
                    </html>';

                    return response($html);
                    
                } catch (\Exception $e) {
                    return response('Error: ' . $e->getMessage(), 500);
                }
            });
            
            // List All Tables
            Route::get('/all-tables', function() {
                try {
                    $tables = \App\Models\Table::all();
                    
                    return response()->json([
                        'success' => true,
                        'data' => $tables->map(function($table) {
                            return [
                                'id' => $table->id,
                                'table_number' => $table->table_number,
                                'qr_code' => $table->qr_code,
                                'status' => $table->status,
                                'qr_url' => url("/api/v1/admin/qr/generate/{$table->id}"),
                                'custom_url' => url("/api/v1/admin/qr/custom/{$table->id}"),
                                'print_url' => url("/api/v1/admin/qr/print/{$table->id}")
                            ];
                        })
                    ]);
                    
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to get tables: ' . $e->getMessage()
                    ], 500);
                }
            });
        });

        // QR Manager Dashboard
        Route::get('/qr-manager', function() {
            $tables = \App\Models\Table::all();
            
            $html = '<!DOCTYPE html>
            <html>
            <head>
                <title>Savor QR Code Manager</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
                    .container { max-width: 1200px; margin: 0 auto; }
                    .header { 
                        background: #e74c3c; 
                        color: white; 
                        padding: 20px; 
                        border-radius: 10px; 
                        margin-bottom: 30px; 
                        text-align: center; 
                    }
                    .bulk-actions {
                        background: white;
                        padding: 20px;
                        border-radius: 10px;
                        margin-bottom: 20px;
                        text-align: center;
                    }
                    .table-grid { 
                        display: grid; 
                        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
                        gap: 20px; 
                    }
                    .table-card { 
                        background: white; 
                        padding: 20px; 
                        border-radius: 10px; 
                        box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
                        text-align: center; 
                    }
                    .table-number { 
                        font-size: 24px; 
                        font-weight: bold; 
                        color: #e74c3c; 
                        margin-bottom: 15px; 
                    }
                    .qr-preview { 
                        width: 200px; 
                        height: 200px; 
                        margin: 0 auto 15px; 
                        border: 1px solid #ddd; 
                    }
                    .btn { 
                        display: inline-block; 
                        padding: 10px 15px; 
                        margin: 5px; 
                        background: #e74c3c; 
                        color: white; 
                        text-decoration: none; 
                        border-radius: 5px; 
                        font-size: 12px; 
                    }
                    .btn:hover { background: #c0392b; }
                    .btn-print { background: #27ae60; }
                    .btn-print:hover { background: #229954; }
                    .btn-custom { background: #3498db; }
                    .btn-custom:hover { background: #2980b9; }
                    .btn-bulk { 
                        padding: 15px 30px; 
                        font-size: 16px; 
                        margin: 10px; 
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🍽️ SAVOR QR Code Manager</h1>
                        <p>Generate dan print QR Code untuk setiap meja</p>
                    </div>
                    
                    <div class="bulk-actions">
                        <h2>Bulk Actions</h2>
                        <a href="/api/v1/admin/qr/print-all" class="btn btn-print btn-bulk" target="_blank">
                            🖨️ Print All QR Codes (Standard)
                        </a>
                        <a href="/api/v1/admin/qr/print-all-compact" class="btn btn-print btn-bulk" target="_blank">
                            🖨️ Print All QR Codes (Compact)
                        </a>
                    </div>
                    
                    <div class="table-grid">';
            
            foreach($tables as $table) {
                $html .= '
                        <div class="table-card">
                            <div class="table-number">MEJA ' . $table->table_number . '</div>
                            <div class="qr-preview">
                                <img src="/api/v1/admin/qr/generate/' . $table->id . '" width="200" height="200" alt="QR Code Meja ' . $table->table_number . '">
                            </div>
                            <p>Status: <strong>' . $table->status . '</strong></p>
                            <a href="/api/v1/admin/qr/print/' . $table->id . '" class="btn btn-print" target="_blank">Print QR</a>
                            <a href="/api/v1/admin/qr/custom/' . $table->id . '" class="btn btn-custom" target="_blank">Custom Design</a>
                            <a href="/api/v1/admin/qr/generate/' . $table->id . '" class="btn" target="_blank">View SVG</a>
                        </div>';
            }
            
            $html .= '
                    </div>
                </div>
            </body>
            </html>';
            
            return $html;
        });
    });

    // ==========================================
    // STOCK MANAGEMENT
    // ==========================================
    Route::prefix('stock')->group(function () {
        Route::get('/check/{menuId}', function($menuId) {
            $menu = \App\Models\Menu::find($menuId);
            
            if (!$menu) {
                return response()->json(['success' => false, 'message' => 'Menu not found'], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'menu_name' => $menu->name,
                    'current_stock' => $menu->stock_quantity,
                    'minimum_stock' => $menu->minimum_stock,
                    'is_low_stock' => $menu->stock_quantity <= $menu->minimum_stock,
                    'is_available' => $menu->is_available && $menu->stock_quantity > 0
                ]
            ]);
        });
        
        Route::get('/low-stock', function() {
            $lowStockMenus = \App\Models\Menu::whereColumn('stock_quantity', '<=', 'minimum_stock')
                                            ->with('category')
                                            ->get();
            
            return response()->json([
                'success' => true,
                'data' => $lowStockMenus,
                'count' => $lowStockMenus->count()
            ]);
        });
        
        Route::post('/replenish/{menuId}', function($menuId, \Illuminate\Http\Request $request) {
            $request->validate([
                'quantity' => 'required|integer|min:1'
            ]);
            
            $menu = \App\Models\Menu::find($menuId);
            
            if (!$menu) {
                return response()->json(['success' => false, 'message' => 'Menu not found'], 404);
            }
            
            $menu->increment('stock_quantity', $request->quantity);
            
            return response()->json([
                'success' => true,
                'message' => "Stock {$menu->name} berhasil ditambah {$request->quantity}",
                'data' => [
                    'menu_name' => $menu->name,
                    'new_stock' => $menu->fresh()->stock_quantity
                ]
            ]);
        });
    });

    // ==========================================
    // TEST EMAIL
    // ==========================================
    Route::get('/test-gmail', function() {
        try {
            $testEmail = 'alieffathur123@gmail.com';
            
            \Illuminate\Support\Facades\Mail::raw('🎉 Test email from Savor QR Order System!', function ($message) use ($testEmail) {
                $message->to($testEmail)
                        ->subject('Test Email from Savor');
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully! Check your Gmail inbox.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    });

    // ==========================================
    // DEVELOPMENT/TESTING ENDPOINTS
    // ==========================================
    Route::prefix('test')->group(function () {
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
        
        Route::post('/reset-tables', function() {
            \App\Models\Table::query()->update(['status' => 'Free']);
            return response()->json([
                'success' => true,
                'message' => 'Semua table status direset ke Free'
            ]);
        });
        
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
            
            try {
                $emailService = new \App\Services\EmailService();
                $emailResult = $emailService->sendPaymentReceipt($order->fresh(['customer', 'table', 'items.menu']));
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment simulated successfully',
                    'data' => $order->fresh(),
                    'email_sent' => $emailResult
                ]);
                
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send receipt email after simulation', [
                    'order_uuid' => $orderUuid,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment simulated successfully, but email failed',
                    'data' => $order->fresh(),
                    'email_sent' => false,
                    'email_error' => $e->getMessage()
                ]);
            }
        });
    });
});

// ==========================================
// STAFF ROUTES
// ==========================================
Route::prefix('staff')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        
        Route::prefix('kitchen')->group(function () {
            Route::get('/queue', [KitchenController::class, 'getQueue']);
            Route::post('/items/{itemId}/done', [KitchenController::class, 'markItemDone']);
            Route::get('/dashboard', [KitchenController::class, 'getDashboard']);
            Route::get('/completed', [KitchenController::class, 'getCompletedItems']);
        });
    });
});

// ==========================================
// HEALTH CHECK & API INFO
// ==========================================
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