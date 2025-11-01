<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Table;
use App\Models\Menu;
use App\Models\Setting;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }


    /**
     * Create new order with tax & service charge calculation
     * 
     * POST /api/v1/orders
     */
    public function store(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'email' => 'required|email',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.special_notes' => 'nullable|string|max:500',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // 1. Validasi customer & session
            $customer = Customer::where('session_token', $request->session_token)->first();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak valid'
                ], 401);
            }

            // 2. Update email customer
            $customer->update(['email' => $request->email]);

            // 3. Get table
            // Coba get table_id dari customer dulu
            $table = null;
            if (isset($customer->table_id)) {
                $table = Table::find($customer->table_id);
            }
            
            // Fallback: cari table yang Occupied
            if (!$table) {
                $table = Table::where('status', 'Occupied')->first();
            }
            
            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meja tidak ditemukan'
                ], 404);
            }

            // 4. STOCK VALIDATION - Check semua items dulu sebelum create order
            $stockErrors = [];
            $orderItems = [];
            $itemsSubtotal = 0;

            foreach ($request->items as $item) {
                $menu = Menu::find($item['menu_id']);
                
                // Check stock availability
                if ($menu->stock_quantity < $item['quantity']) {
                    $stockErrors[] = [
                        'menu_name' => $menu->name,
                        'requested' => $item['quantity'],
                        'available' => $menu->stock_quantity
                    ];
                    continue;
                }

                $subtotal = $menu->price * $item['quantity'];
                $itemsSubtotal += $subtotal;

                $orderItems[] = [
                    'menu' => $menu,
                    'quantity' => $item['quantity'],
                    'price' => $menu->price,
                    'subtotal' => $subtotal,
                    'special_notes' => $item['special_notes'] ?? null,
                    'status' => 'Pending'
                ];
            }

            // Jika ada stock errors, return error
            if (!empty($stockErrors)) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Beberapa item tidak tersedia',
                    'data' => [
                        'stock_errors' => $stockErrors,
                        'available_items' => array_map(function($item) {
                            return [
                                'menu_name' => $item['menu']->name,
                                'available_stock' => $item['menu']->stock_quantity
                            ];
                        }, $orderItems)
                    ]
                ], 400);
            }

            // 5. Generate order number
            $orderNumber = $this->generateOrderNumber();

            // 6. Create order instance (jangan save dulu)
            $order = new Order([
                'order_uuid' => (string) Str::uuid(),
                'order_number' => $orderNumber,
                'customer_id' => $customer->id,
                'table_id' => $table->id,
                'notes' => $request->notes,
                'payment_status' => 'Pending',
                'session_expires_at' => now()->addHours(2)
            ]);

            // 7. Calculate pricing dengan tax & service charge
            $order->calculatePricing($itemsSubtotal);

            // 8. Save order
            $order->save();

            // 9. Create order items AND update stock
            foreach ($orderItems as $itemData) {
                $order->items()->create([
                    'menu_id' => $itemData['menu']->id,
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'subtotal' => $itemData['subtotal'],
                    'special_notes' => $itemData['special_notes'],
                    'status' => $itemData['status']
                ]);

                // UPDATE STOCK - Reduce quantity
                $itemData['menu']->decrement('stock_quantity', $itemData['quantity']);
            }

            DB::commit();

            // 10. Send order confirmation email
            $emailSent = false;
            try {
                $this->emailService->sendOrderConfirmation($order);
                $emailSent = true;
            } catch (\Exception $e) {
                Log::warning('Failed to send order email: ' . $e->getMessage());
            }

            // 11. Return response dengan breakdown lengkap
            return response()->json([
                'success' => true,
                'message' => 'Order berhasil dibuat',
                'data' => [
                    'order_uuid' => $order->order_uuid,
                    'order_number' => $order->order_number,
                    'breakdown' => $order->breakdown,
                    'items_count' => count($orderItems),
                    'table_number' => $table->table_number,
                    'email_sent' => $emailSent,
                    'payment_status' => $order->payment_status,
                    'session_expires_at' => $order->session_expires_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Order creation failed: ' . $e->getMessage(), [
                'session_token' => $request->session_token,
                'items' => $request->items,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat order',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get order by UUID
     * 
     * GET /api/v1/orders/{uuid}
     */
    public function show($uuid)
    {
        try {
            $order = Order::with(['items.menu', 'table', 'customer'])
                          ->where('order_uuid', $uuid)
                          ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve order: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data order'
            ], 500);
        }
    }

    /**
     * Calculate order preview (before creating order)
     * 
     * POST /api/v1/orders/calculate
     * 
     * Body: {
     *   "items": [
     *     { "menu_id": 1, "quantity": 2 },
     *     { "menu_id": 5, "quantity": 1 }
     *   ]
     * }
     */
    public function calculatePreview(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        try {
            $itemsSubtotal = 0;
            $itemsDetail = [];

            foreach ($request->items as $item) {
                $menu = Menu::find($item['menu_id']);
                $subtotal = $menu->price * $item['quantity'];
                $itemsSubtotal += $subtotal;

                $itemsDetail[] = [
                    'menu_id' => $menu->id,
                    'menu_name' => $menu->name,
                    'price' => (float) $menu->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal
                ];
            }

            // Calculate pricing breakdown using Order model static method
            $breakdown = Order::calculatePricingBreakdown($itemsSubtotal);

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $itemsDetail,
                    'breakdown' => $breakdown
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Calculate preview failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghitung preview order'
            ], 500);
        }
    }

    /**
     * Get order history by device ID (permanent history)
     * 
     * GET /api/v1/orders/history/device
     * Query param: device_id
     */
    public function deviceHistory(Request $request)
    {
        try {
            $deviceId = $request->device_id;
            
            if (!$deviceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'device_id required'
                ], 400);
            }

            \Log::info('Device history request', ['device_id' => $deviceId]);

            // Get customers
            $customers = Customer::where('device_id', $deviceId)->get();
            
            \Log::info('Customers found', ['count' => $customers->count()]);

            if ($customers->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'orders' => [],
                        'total_orders' => 0,
                        'total_spent' => 0
                    ]
                ]);
            }

            $customerIds = $customers->pluck('id')->toArray();
            
            \Log::info('Customer IDs', ['ids' => $customerIds]);

            // Get orders - NO circular relations
            $orders = Order::select([
                    'id', 'order_uuid', 'order_number', 'customer_id', 'table_id',
                    'subtotal', 'service_charge_amount', 'tax_amount', 'total_amount',
                    'payment_status', 'payment_reference', 'notes', 'paid_at', 'created_at'
                ])
                ->whereIn('customer_id', $customerIds)
                ->with([
                    'items:id,order_id,menu_id,quantity,price,subtotal',
                    'items.menu:id,name,price',
                    'table:id,table_number'
                ])
                ->orderBy('created_at', 'desc')
                ->limit(50) // Limit untuk testing
                ->get();

            \Log::info('Orders found', ['count' => $orders->count()]);

            $totalSpent = Order::whereIn('customer_id', $customerIds)
                            ->where('payment_status', 'Paid')
                            ->sum('total_amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $orders,
                    'total_orders' => $orders->count(),
                    'total_spent' => (float) $totalSpent
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Device history error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order history by session token (original method - UPDATED)
     * 
     * GET /api/v1/orders/history/{sessionToken}
     */
    public function history($sessionToken)
    {
        try {
            $customer = Customer::where('session_token', $sessionToken)->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session tidak valid'
                ], 401);
            }

            // Get orders dari customer ini (include expired)
            // Tapi prioritaskan session aktif di response
            $allOrders = Order::with(['items.menu', 'table'])
                            ->where('customer_id', $customer->id)
                            ->orderBy('created_at', 'desc')
                            ->get();

            // Separate current session vs history
            $currentSessionOrders = $allOrders->filter(function($order) {
                return $order->session_expires_at > now();
            })->values();

            $pastOrders = $allOrders->filter(function($order) {
                return $order->session_expires_at <= now();
            })->values();

            // Calculate totals
            $totalSpent = Order::where('customer_id', $customer->id)
                            ->where('payment_status', 'Paid')
                            ->sum('total_amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'email' => $customer->email,
                        'device_id' => $customer->device_id
                    ],
                    'current_session' => $currentSessionOrders,
                    'past_orders' => $pastOrders,
                    'all_orders' => $allOrders,
                    'total_orders' => $allOrders->count(),
                    'total_spent' => (float) $totalSpent
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve order history: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil riwayat order'
            ], 500);
        }
    }

    /**
     * Generate unique order number
     * 
     * @return string
     */
    private function generateOrderNumber()
    {
        $date = now()->format('Ymd');
        $lastOrder = Order::whereDate('created_at', today())
                          ->orderBy('id', 'desc')
                          ->first();

        $sequence = $lastOrder ? intval(substr($lastOrder->order_number, -3)) + 1 : 1;
        
        return 'ORD-' . $date . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }
}