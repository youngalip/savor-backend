<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Category;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    /**
     * Get order queue for specific kitchen division (Kitchen/Bar/Pastry)
     */
    public function getQueue(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get category based on user role
            $categoryName = $this->getCategoryByRole($user->role);
            
            if (!$categoryName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role for kitchen access'
                ], 403);
            }

            // Get category ID
            $category = Category::where('name', $categoryName)->first();
            
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            // Get PENDING order items for this category (paid orders only)
            $orderItems = OrderItem::with(['order.table', 'order.customer', 'menu'])
                                  ->whereHas('menu', function($query) use ($category) {
                                      $query->where('category_id', $category->id);
                                  })
                                  ->whereHas('order', function($query) {
                                      $query->where('payment_status', 'Paid');
                                  })
                                  ->where('status', 'Pending') // Only pending items
                                  ->orderBy('created_at', 'asc') // FIFO queue
                                  ->get()
                                  ->groupBy('order_id');

            // Format response by order
            $queue = [];
            foreach ($orderItems as $orderId => $items) {
                $order = $items->first()->order;
                
                $queue[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'table_number' => $order->table->table_number,
                    'order_time' => $order->created_at->format('H:i'),
                    'waiting_time' => $this->calculateWaitingTime($order->created_at),
                    'total_items' => $items->count(),
                    'items' => $items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'menu_name' => $item->menu->name,
                            'quantity' => $item->quantity,
                            'special_notes' => $item->special_notes,
                            'status' => $item->status,
                            'preparation_time' => $item->menu->preparation_time
                        ];
                    })
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'division' => $categoryName,
                    'queue_count' => count($queue),
                    'total_pending_items' => $orderItems->flatten()->count(),
                    'queue' => $queue
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get kitchen queue'
            ], 500);
        }
    }

    /**
     * Mark order item as DONE (swipe to complete)
     */
    public function markItemDone(Request $request, $itemId)
    {
        try {
            $user = $request->user();
            $categoryName = $this->getCategoryByRole($user->role);

            $orderItem = OrderItem::with(['menu.category', 'order.table'])
                                 ->where('id', $itemId)
                                 ->first();

            if (!$orderItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order item not found'
                ], 404);
            }

            // Check if item belongs to user's division
            if ($orderItem->menu->category->name !== $categoryName) {
                return response()->json([
                    'success' => false,
                    'message' => 'This item belongs to different division'
                ], 403);
            }

            // Check if order is paid
            if ($orderItem->order->payment_status !== 'Paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not paid yet'
                ], 400);
            }

            // Check current status
            if ($orderItem->status === 'Done') {
                return response()->json([
                    'success' => false,
                    'message' => 'Item already completed'
                ], 400);
            }

            // Mark as DONE
            $orderItem->update(['status' => 'Done']);

            return response()->json([
                'success' => true,
                'message' => 'Item marked as done! ✅',
                'data' => [
                    'item_id' => $orderItem->id,
                    'menu_name' => $orderItem->menu->name,
                    'quantity' => $orderItem->quantity,
                    'order_number' => $orderItem->order->order_number,
                    'table_number' => $orderItem->order->table->table_number,
                    'completed_at' => now()->format('H:i')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark item as done'
            ], 500);
        }
    }

    /**
     * Get division dashboard statistics
     */
    public function getDashboard(Request $request)
    {
        try {
            $user = $request->user();
            $categoryName = $this->getCategoryByRole($user->role);
            
            $category = Category::where('name', $categoryName)->first();

            $stats = [
                'division' => $categoryName,
                'staff_name' => $user->name,
                
                // Current queue stats
                'pending_items' => OrderItem::whereHas('menu', function($query) use ($category) {
                                             $query->where('category_id', $category->id);
                                         })
                                         ->whereHas('order', function($query) {
                                             $query->where('payment_status', 'Paid');
                                         })
                                         ->where('status', 'Pending')
                                         ->count(),
                
                // Today's performance
                'completed_today' => OrderItem::whereHas('menu', function($query) use ($category) {
                                              $query->where('category_id', $category->id);
                                          })
                                          ->where('status', 'Done')
                                          ->whereDate('updated_at', today())
                                          ->count(),
                
                'orders_today' => Order::whereHas('items.menu', function($query) use ($category) {
                                        $query->where('category_id', $category->id);
                                    })
                                    ->where('payment_status', 'Paid')
                                    ->whereDate('created_at', today())
                                    ->distinct()
                                    ->count(),
                
                // Quick metrics
                'oldest_pending' => $this->getOldestPendingTime($category->id),
                'current_time' => now()->format('H:i')
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard data'
            ], 500);
        }
    }

    /**
     * Get completed items for today (for reference)
     */
    public function getCompletedItems(Request $request)
    {
        try {
            $user = $request->user();
            $categoryName = $this->getCategoryByRole($user->role);
            
            $category = Category::where('name', $categoryName)->first();

            $completedItems = OrderItem::with(['order.table', 'menu'])
                                      ->whereHas('menu', function($query) use ($category) {
                                          $query->where('category_id', $category->id);
                                      })
                                      ->where('status', 'Done')
                                      ->whereDate('updated_at', today())
                                      ->orderBy('updated_at', 'desc')
                                      ->take(20) // Last 20 completed items
                                      ->get()
                                      ->map(function($item) {
                                          return [
                                              'id' => $item->id,
                                              'menu_name' => $item->menu->name,
                                              'quantity' => $item->quantity,
                                              'order_number' => $item->order->order_number,
                                              'table_number' => $item->order->table->table_number,
                                              'completed_at' => $item->updated_at->format('H:i')
                                          ];
                                      });

            return response()->json([
                'success' => true,
                'data' => [
                    'division' => $categoryName,
                    'completed_items' => $completedItems,
                    'total_completed_today' => $completedItems->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get completed items'
            ], 500);
        }
    }

    /**
     * Helper: Map user role to category name
     */
    private function getCategoryByRole($role)
    {
        $roleMapping = [
            'Kitchen' => 'Kitchen',
            'Bar' => 'Bar', 
            'Pastry' => 'Pastry'
        ];

        return $roleMapping[$role] ?? null;
    }

    /**
     * Helper: Calculate waiting time for order
     */
    private function calculateWaitingTime($orderTime)
    {
        $diffMinutes = now()->diffInMinutes($orderTime);
        
        if ($diffMinutes < 60) {
            return $diffMinutes . ' min';
        } else {
            $hours = floor($diffMinutes / 60);
            $minutes = $diffMinutes % 60;
            return $hours . 'h ' . $minutes . 'm';
        }
    }

    /**
     * Helper: Get oldest pending order time
     */
    private function getOldestPendingTime($categoryId)
    {
        $oldestItem = OrderItem::whereHas('menu', function($query) use ($categoryId) {
                                  $query->where('category_id', $categoryId);
                              })
                              ->whereHas('order', function($query) {
                                  $query->where('payment_status', 'Paid');
                              })
                              ->where('status', 'Pending')
                              ->orderBy('created_at', 'asc')
                              ->first();

        if (!$oldestItem) {
            return 'No pending items';
        }

        return $this->calculateWaitingTime($oldestItem->created_at);
    }
}
