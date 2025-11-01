<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V7 - CORRECTED BASED ON ACTUAL DATABASE
 * 
 * From orders.csv analysis:
 * - table_id IS the table number itself (1, 2, 3, etc) - NOT a foreign key!
 * - customer_id might be foreign key OR just a number
 * - payment_method does NOT exist - must derive from payment_reference
 * - order_time does NOT exist - use created_at
 * - completed_at exists and works correctly
 */
class CashierController extends Controller
{
    /**
     * Get orders with filters
     */
    public function getOrders(Request $request)
    {
        try {
            // Start with items relationship only
            $query = Order::with(['items.menu.category'])
                ->where('payment_status', '!=', 'Failed');

            // Filter by payment status
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Filter by table_id (which IS the table number)
            if ($request->has('table_id')) {
                $query->where('table_id', $request->table_id);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Exclude completed orders (for active orders pages)
            if ($request->boolean('exclude_completed')) {
                $query->whereNull('completed_at');
            }

            // Filter by calculated status
            if ($request->has('status')) {
                $status = $request->status;
                
                if ($status === 'completed') {
                    $query->whereNotNull('completed_at');
                } else {
                    $query->whereNull('completed_at');
                }
            }

            // Filter by category (through items)
            if ($request->has('category')) {
                $category = $request->category;
                $query->whereHas('items.menu.category', function ($q) use ($category) {
                    $q->where('name', $category);
                });
            }

            // Order by newest first
            $query->orderBy('created_at', 'desc');

            $orders = $query->get();

            // Transform orders
            $ordersData = $orders->map(function ($order) {
                return $this->transformOrder($order);
            });

            // Filter by calculated status (if not completed)
            if ($request->has('status') && $request->status !== 'completed') {
                $ordersData = $ordersData->filter(function ($order) use ($request) {
                    return $order['status'] === $request->status;
                });
            }

            // Exclude ready orders (for processing page)
            if ($request->boolean('exclude_ready')) {
                $ordersData = $ordersData->filter(function ($order) {
                    return $order['status'] !== 'ready' && 
                           $order['status'] !== 'completed';
                });
            }

            Log::info('Orders fetched', [
                'filters' => $request->all(),
                'count' => $ordersData->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $ordersData->values(),
                'count' => $ordersData->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch orders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single order detail
     */
    public function getOrder($id)
    {
        try {
            $order = Order::with(['items.menu.category'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $this->transformOrder($order)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch order', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Validate cash payment
     */
    public function validatePayment($id)
    {
        try {
            $order = Order::findOrFail($id);

            // Check if already paid
            if ($order->payment_status === 'Paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already validated'
                ], 400);
            }

            // Update payment status
            $order->update([
                'payment_status' => 'Paid',
                'paid_at' => now(),
                'payment_reference' => 'CASH-' . $order->order_number
            ]);

            Log::info('Payment validated', [
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment validated successfully',
                'data' => $this->transformOrder($order->fresh(['items.menu.category']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to validate payment', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark order as completed
     * CRITICAL: This is the function for "Tandai Selesai" button
     */
    public function markCompleted($id)
    {
        try {
            Log::info('🔄 Mark completed started', [
                'order_id' => $id
            ]);

            $order = Order::with('items')->findOrFail($id);

            Log::info('Order found', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'completed_at' => $order->completed_at
            ]);

            // Check if already completed
            if ($order->completed_at) {
                Log::warning('Order already completed', [
                    'order_id' => $order->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Order already completed'
                ], 400);
            }

            // Check if payment is done
            if ($order->payment_status !== 'Paid') {
                Log::warning('Payment not done', [
                    'order_id' => $order->id,
                    'payment_status' => $order->payment_status
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot complete order. Payment not yet done.'
                ], 400);
            }

            // Check if all items are done
            $itemsStatus = $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'menu_id' => $item->menu_id,
                    'status' => $item->status
                ];
            });

            Log::info('Items status', [
                'order_id' => $order->id,
                'items' => $itemsStatus
            ]);

            $allDone = $order->items->every(function ($item) {
                return $item->status === 'Done';
            });

            if (!$allDone) {
                Log::warning('Not all items done', [
                    'order_id' => $order->id,
                    'items_status' => $itemsStatus
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot complete order. Not all items are done.',
                    'items_status' => $itemsStatus
                ], 400);
            }

            // Mark as completed
            $order->completed_at = now();
            $order->save();

            Log::info('✅ Order completed successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'completed_at' => $order->completed_at
            ]);

            // Reload with fresh data
            $order->load('items.menu.category');

            return response()->json([
                'success' => true,
                'message' => 'Order marked as completed successfully',
                'data' => $this->transformOrder($order)
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to complete order', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $query = Order::where('payment_status', 'Paid')
                         ->whereNotNull('completed_at');

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('completed_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('completed_at', '<=', $request->date_to);
            }

            // Calculate statistics
            $totalOrders = $query->count();
            $totalRevenue = $query->sum('total_amount');
            
            $stats = [
                'total_orders' => $totalOrders,
                'total_revenue' => (float) $totalRevenue,
                'average_order' => $totalOrders > 0 
                    ? (float) ($totalRevenue / $totalOrders)
                    : 0
            ];

            Log::info('Statistics fetched', [
                'filters' => $request->all(),
                'stats' => $stats
            ]);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transform order
     * V7: Simplified - table_id IS the table number
     */
    private function transformOrder($order)
    {
        // Calculate order status
        $status = $this->calculateOrderStatus($order);

        // Derive payment method from payment_reference
        $paymentMethod = 'Unknown';
        if ($order->payment_reference) {
            if (str_starts_with($order->payment_reference, 'CASH-')) {
                $paymentMethod = 'Cash';
            } elseif (str_starts_with($order->payment_reference, 'SIM_')) {
                $paymentMethod = 'Simulator';
            } else {
                $paymentMethod = 'Online Payment';
            }
        } elseif ($order->payment_status === 'Pending') {
            $paymentMethod = 'Pending';
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'table_id' => $order->table_id,
            'table_number' => $order->table_id, // table_id IS the table number!
            'customer_name' => 'Customer', // Simplified - could add logic later
            'customer_email' => null,
            'order_time' => $order->created_at,
            'status' => $status,
            'payment_status' => $order->payment_status,
            'payment_method' => $paymentMethod,
            'payment_reference' => $order->payment_reference,
            'paid_at' => $order->paid_at,
            'subtotal' => (float) $order->subtotal,
            'service_charge_rate' => (float) $order->service_charge_rate,
            'service_charge_amount' => (float) $order->service_charge_amount,
            'tax_rate' => (float) $order->tax_rate,
            'tax_amount' => (float) $order->tax_amount,
            'total_amount' => (float) $order->total_amount,
            'notes' => $order->notes,
            'completed_at' => $order->completed_at,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->menu ? $item->menu->name : 'Unknown',
                    'category' => $item->menu && $item->menu->category 
                        ? $item->menu->category->name 
                        : 'Unknown',
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                    'status' => $item->status,
                    'notes' => $item->notes
                ];
            })
        ];
    }

    /**
     * Calculate order status
     */
    private function calculateOrderStatus($order)
    {
        // If completed
        if ($order->completed_at) {
            return 'completed';
        }

        // If payment not done
        if ($order->payment_status !== 'Paid') {
            return 'unpaid';
        }

        // Check items
        $items = $order->items;
        
        if ($items->isEmpty()) {
            return 'pending';
        }

        // If ALL items Done → ready
        $allDone = $items->every(fn($item) => $item->status === 'Done');

        if ($allDone) {
            return 'ready';
        }

        // Default: pending
        return 'pending';
    }
}