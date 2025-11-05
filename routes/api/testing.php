<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Testing & Development API Routes
|--------------------------------------------------------------------------
| Routes untuk testing dan development
| DISABLE in production!
*/

Route::prefix('test')->name('test.')->group(function () {
    
    // View data
    Route::get('/tables', function() {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Table::all()
        ]);
    })->name('tables');
    
    Route::get('/customers', function() {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Customer::latest()->take(10)->get()
        ]);
    })->name('customers');
    
    Route::get('/orders', function() {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Order::with(['items.menu', 'table', 'customer'])
                                      ->latest()
                                      ->take(5)
                                      ->get()
        ]);
    })->name('orders');
    
    // Reset tables
    Route::post('/reset-tables', function() {
        \App\Models\Table::query()->update(['status' => 'Free']);
        return response()->json([
            'success' => true,
            'message' => 'Semua table status direset ke Free'
        ]);
    })->name('reset-tables');
    
    // Simulate payment
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
    })->name('simulate-payment');
    
    // Test email
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
    })->name('test-gmail');
});