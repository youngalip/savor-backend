<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Table;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SessionService
{
    /**
     * Cleanup expired sessions and reset table status
     */
    public function cleanupExpiredSessions()
    {
        try {
            // Find all expired orders
            $expiredOrders = Order::where('session_expires_at', '<', now())
                                 ->where('payment_status', '!=', 'Paid')
                                 ->get();

            $cleanedTables = [];
            $cleanedSessions = 0;

            foreach ($expiredOrders as $order) {
                // Reset table status to Free
                $table = $order->table;
                if ($table && $table->status === 'Occupied') {
                    $table->update(['status' => 'Free']);
                    $cleanedTables[] = $table->table_number;
                }

                // Mark order as failed
                $order->update(['payment_status' => 'Failed']);
                
                $cleanedSessions++;
            }

            // Clean old customer sessions (older than 24 hours)
            Customer::where('last_activity', '<', now()->subHours(24))
                   ->delete();

            Log::info('Session cleanup completed', [
                'expired_orders' => $cleanedSessions,
                'freed_tables' => $cleanedTables,
                'cleaned_customers' => Customer::where('last_activity', '<', now()->subHours(24))->count()
            ]);

            return [
                'success' => true,
                'message' => 'Session cleanup completed',
                'data' => [
                    'expired_orders' => $cleanedSessions,
                    'freed_tables' => $cleanedTables
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Session cleanup failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Session cleanup failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if customer session is still valid
     */
    public function validateSession($sessionToken)
    {
        $customer = Customer::where('session_token', $sessionToken)->first();

        if (!$customer) {
            return ['valid' => false, 'message' => 'Session not found'];
        }

        // Check if any order in this session is still active
        $activeOrder = Order::where('customer_id', $customer->id)
                           ->where('session_expires_at', '>', now())
                           ->first();

        if (!$activeOrder) {
            return ['valid' => false, 'message' => 'Session expired'];
        }

        // Update last activity
        $customer->update(['last_activity' => now()]);

        return [
            'valid' => true,
            'customer' => $customer,
            'expires_at' => $activeOrder->session_expires_at
        ];
    }

    /**
     * Extend session if customer makes new order
     */
    public function extendSession($customerId, $additionalHours = 2)
    {
        $activeOrders = Order::where('customer_id', $customerId)
                            ->where('session_expires_at', '>', now())
                            ->get();

        foreach ($activeOrders as $order) {
            $order->update([
                'session_expires_at' => now()->addHours($additionalHours)
            ]);
        }

        return [
            'success' => true,
            'new_expiry' => now()->addHours($additionalHours),
            'extended_orders' => $activeOrders->count()
        ];
    }
}