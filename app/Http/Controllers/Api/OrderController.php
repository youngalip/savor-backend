<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Table;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
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

            // 3. Cari table yang sedang occupied oleh customer ini
            $table = Table::where('status', 'Occupied')->first(); // Simplified untuk demo
            
            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meja tidak ditemukan'
                ], 404);
            }

            // 4. Hitung total amount
            $totalAmount = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $menu = Menu::find($item['menu_id']);
                $subtotal = $menu->price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItems[] = [
                    'menu_id' => $menu->id,
                    'quantity' => $item['quantity'],
                    'price' => $menu->price,
                    'subtotal' => $subtotal,
                    'special_notes' => $item['special_notes'] ?? null,
                    'status' => 'Pending'
                ];
            }

            // 5. Generate order number
            $orderNumber = $this->generateOrderNumber();

            // 6. Create order
            $order = Order::create([
                'order_uuid' => (string) Str::uuid(),
                'order_number' => $orderNumber,
                'customer_id' => $customer->id,
                'table_id' => $table->id,
                'total_amount' => $totalAmount,
                'payment_status' => 'Pending',
                'notes' => $request->notes,
                'session_expires_at' => now()->addHours(2)
            ]);

            // 7. Create order items
            foreach ($orderItems as $itemData) {
                $order->items()->create($itemData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil dibuat',
                'data' => [
                    'order_uuid' => $order->order_uuid,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'items_count' => count($orderItems),
                    'table_number' => $table->table_number
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat order'
            ], 500);
        }
    }

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
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data order'
            ], 500);
        }
    }

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