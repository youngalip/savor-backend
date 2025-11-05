<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Menu;
use App\Services\StationAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StationController extends Controller
{
    /**
     * Get order items berdasarkan station type (kitchen/bar/pastry)
     * Dikelompokkan per meja
     * 
     * GET /api/v1/staff/stations/{station_type}/orders
     * 
     * @param string $stationType kitchen|bar|pastry
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrdersByStation(string $stationType, Request $request)
    {
        // Validasi station type
        if (!StationAssignmentService::isValidStation($stationType)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid station type. Must be: kitchen, bar, or pastry',
            ], 400);
        }

        // Get category IDs untuk station ini
        $categoryIds = StationAssignmentService::getCategoryIdsByStation($stationType);

        if (empty($categoryIds)) {
            return response()->json([
                'success' => true,
                'message' => 'No categories found for this station',
                'data' => [
                    'station_type' => $stationType,
                    'orders' => [],
                ],
            ]);
        }

        // Query order items
        $query = OrderItem::with([
            'menu.category',
            'order.table'
        ])
        ->whereHas('menu', function ($q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds);
        })
        ->whereHas('order', function ($q) {
            // Hanya ambil order yang sudah dibayar
            $q->where('payment_status', 'Paid');
        });

        // Filter by status (optional)
        if ($request->has('status')) {
            $status = $request->input('status');
            if (in_array(ucfirst(strtolower($status)), ['Pending', 'Done'])) {
                $query->where('status', ucfirst(strtolower($status)));
            }
        } else {
            // Default: hanya tampilkan yang pending
            $query->where('status', 'Pending');
        }

        // Filter by date (optional)
        if ($request->has('date')) {
            $date = $request->input('date');
            $query->whereDate('created_at', $date);
        }

        $orderItems = $query->orderBy('created_at', 'asc')->get();

        // Group by table
        $groupedByTable = $orderItems->groupBy(function ($item) {
            return $item->order->table_id;
        });

        // Format response
        $ordersData = [];
        foreach ($groupedByTable as $tableId => $items) {
            $firstItem = $items->first();
            $table = $firstItem->order->table;
            $order = $firstItem->order;

            $ordersData[] = [
                'table_id' => $table->id,
                'table_number' => $table->table_number,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_created_at' => $order->created_at->toDateTimeString(),
                'customer_name' => $order->customer_name ?? 'Guest',
                'items_count' => $items->count(),
                'items' => $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'menu_id' => $item->menu_id,
                        'menu_name' => $item->menu->name,
                        'category_name' => $item->menu->category->name,
                        'quantity' => $item->quantity,
                        'status' => strtolower($item->status), // Convert to lowercase for frontend
                        'special_notes' => $item->special_notes ?? null,
                        'created_at' => $item->created_at->toDateTimeString(),
                    ];
                })->values(),
            ];
        }

        // Sort by order created time (oldest first)
        usort($ordersData, function ($a, $b) {
            return strtotime($a['order_created_at']) - strtotime($b['order_created_at']);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'station_type' => $stationType,
                'total_orders' => count($ordersData),
                'total_items' => $orderItems->count(),
                'orders' => $ordersData,
            ],
        ]);
    }

    /**
     * Update status order item (Pending -> Done)
     * 
     * PATCH /api/v1/staff/stations/items/{id}/status
     * 
     * @param int $itemId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateItemStatus($itemId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Pending,Done,pending,done',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $orderItem = OrderItem::with(['menu.category', 'order'])->find($itemId);

        if (!$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Order item not found',
            ], 404);
        }

        // Validasi: order harus sudah dibayar
        if ($orderItem->order->payment_status !== 'Paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update status. Order is not Paid yet.',
            ], 400);
        }

        $oldStatus = $orderItem->status;
        $newStatus = ucfirst(strtolower($request->input('status'))); // Normalize to Pending/Done

        $orderItem->status = $newStatus;
        $orderItem->save();

        return response()->json([
            'success' => true,
            'message' => 'Order item status updated successfully',
            'data' => [
                'id' => $orderItem->id,
                'menu_name' => $orderItem->menu->name,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_at' => $orderItem->updated_at->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Batch update status untuk multiple items
     * 
     * POST /api/v1/staff/stations/items/batch-update-status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_ids' => 'required|array',
            'item_ids.*' => 'required|integer|exists:order_items,id',
            'status' => 'required|string|in:Pending,Done,pending,done',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $itemIds = $request->input('item_ids');
        $newStatus = ucfirst(strtolower($request->input('status'))); // Normalize

        DB::beginTransaction();
        try {
            $updated = OrderItem::whereIn('id', $itemIds)
                ->whereHas('order', function ($q) {
                    $q->where('payment_status', 'Paid');
                })
                ->update(['status' => $newStatus]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updated} items",
                'data' => [
                    'updated_count' => $updated,
                    'status' => $newStatus,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get menu list untuk station tertentu
     * Untuk keperluan edit stock
     * 
     * GET /api/v1/staff/stations/{station_type}/menus
     * 
     * @param string $stationType
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenusByStation(string $stationType)
    {
        if (!StationAssignmentService::isValidStation($stationType)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid station type',
            ], 400);
        }

        $categoryIds = StationAssignmentService::getCategoryIdsByStation($stationType);

        if (empty($categoryIds)) {
            return response()->json([
                'success' => true,
                'message' => 'No menus found for this station',
                'data' => [],
            ]);
        }

        $menus = Menu::with('category')
            ->whereIn('category_id', $categoryIds)
            ->orderBy('display_order')
            ->get()
            ->map(function ($menu) {
                return [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'category_name' => $menu->category->name,
                    'price' => (float) $menu->price,
                    'stock_quantity' => $menu->stock_quantity ?? null,
                    'minimum_stock' => $menu->minimum_stock ?? null,
                    'is_available' => $menu->is_available ?? true,
                    'image_url' => $menu->image_url ?? null,
                    'preparation_time' => $menu->preparation_time ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'station_type' => $stationType,
                'total_menus' => $menus->count(),
                'menus' => $menus,
            ],
        ]);
    }

    /**
     * Update stock menu (hanya stock_quantity, minimum_stock, is_available)
     * 
     * PATCH /api/v1/staff/stations/{station_type}/menus/{id}/stock
     * 
     * @param string $stationType
     * @param int $menuId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMenuStock(string $stationType, $menuId, Request $request)
    {
        if (!StationAssignmentService::isValidStation($stationType)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid station type',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'stock_quantity' => 'sometimes|integer|min:0',
            'minimum_stock' => 'sometimes|integer|min:0',
            'is_available' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $menu = Menu::with('category')->find($menuId);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found',
            ], 404);
        }

        // Validasi: menu harus sesuai dengan station
        $menuStation = StationAssignmentService::getStationFromCategory($menu->category->name);
        if ($menuStation !== $stationType) {
            return response()->json([
                'success' => false,
                'message' => "This menu belongs to {$menuStation} station, not {$stationType}",
            ], 403);
        }

        // Update hanya field yang diizinkan
        $updated = [];
        if ($request->has('stock_quantity')) {
            $menu->stock_quantity = $request->input('stock_quantity');
            $updated['stock_quantity'] = $menu->stock_quantity;
        }
        if ($request->has('minimum_stock')) {
            $menu->minimum_stock = $request->input('minimum_stock');
            $updated['minimum_stock'] = $menu->minimum_stock;
        }
        if ($request->has('is_available')) {
            $menu->is_available = $request->input('is_available');
            $updated['is_available'] = $menu->is_available;
        }

        $menu->save();

        return response()->json([
            'success' => true,
            'message' => 'Menu stock updated successfully',
            'data' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'updated_fields' => $updated,
                'current_data' => [
                    'stock_quantity' => $menu->stock_quantity,
                    'minimum_stock' => $menu->minimum_stock,
                    'is_available' => $menu->is_available,
                ],
            ],
        ]);
    }

    /**
     * Get dashboard statistics untuk station
     * 
     * GET /api/v1/staff/stations/{station_type}/stats
     * 
     * @param string $stationType
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStationStats(string $stationType, Request $request)
    {
        if (!StationAssignmentService::isValidStation($stationType)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid station type',
            ], 400);
        }

        $categoryIds = StationAssignmentService::getCategoryIdsByStation($stationType);

        // Date filter (default: today)
        $date = $request->input('date', now()->toDateString());

        // Count items by status
        $baseQuery = OrderItem::whereHas('menu', function ($q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds);
        })
        ->whereHas('order', function ($q) {
            $q->where('payment_status', 'Paid');
        })
        ->whereDate('created_at', $date);

        $totalItems = $baseQuery->count();
        $pendingItems = (clone $baseQuery)->where('status', 'Pending')->count();
        $doneItems = (clone $baseQuery)->where('status', 'Done')->count();

        // Get active orders (have pending items)
        $activeOrders = OrderItem::whereHas('menu', function ($q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds);
        })
        ->whereHas('order', function ($q) {
            $q->where('payment_status', 'Paid');
        })
        ->where('status', 'Pending')
        ->whereDate('created_at', $date)
        ->distinct('order_id')
        ->count('order_id');

        // Low stock items (if stock columns exist)
        $lowStockItems = 0;
        try {
            $lowStockItems = Menu::whereIn('category_id', $categoryIds)
                ->whereColumn('stock_quantity', '<=', 'minimum_stock')
                ->where('is_available', true)
                ->count();
        } catch (\Exception $e) {
            // Skip if stock columns don't exist
        }

        return response()->json([
            'success' => true,
            'data' => [
                'station_type' => $stationType,
                'date' => $date,
                'items' => [
                    'total' => $totalItems,
                    'pending' => $pendingItems,
                    'done' => $doneItems,
                    'completion_rate' => $totalItems > 0 ? round(($doneItems / $totalItems) * 100, 2) : 0,
                ],
                'active_orders' => $activeOrders,
                'low_stock_items' => $lowStockItems,
            ],
        ]);
    }
}