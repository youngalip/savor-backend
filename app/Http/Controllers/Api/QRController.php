<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QRController extends Controller
{
    public function scanQR(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
            'device_info' => 'array',
            'device_id' => 'nullable|string'
        ]);

        // 1) Validasi table dari QR
        $table = Table::where('qr_code', $request->qr_code)->first();
        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak valid'
            ], 404);
        }

        // 2) Ambil device_id: prioritas header → body → fallback generate
        $deviceId = $request->header('X-Device-Id') 
            ?: ($request->input('device_id') ?: $this->generateDeviceId($request->device_info ?? [], $request));

        // 3) Cari customer berdasarkan device_id
        $customer = Customer::where('device_id', $deviceId)->first();

        // 4) Kalau ada order aktif dari customer yang sama di meja ini → perpanjang
        if ($customer) {
            $activeOrder = Order::where('customer_id', $customer->id)
                ->where('table_id', $table->id)
                ->where('session_expires_at', '>', now())
                ->first();

            if ($activeOrder) {
                // Update customer activity
                $customer->update([
                    'session_token' => Str::random(32),
                    'last_activity' => now(),
                    'user_agent'    => $request->userAgent(),
                    'ip_address'    => $request->ip(),
                ]);

                // Perpanjang session
                $activeOrder->update([
                    'session_expires_at' => now()->addHours(2)
                ]);

                // Update last_used_at untuk tracking (optional - status tetap informational)
                $table->update([
                    'status' => 'Occupied', // Informational only
                    'updated_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Welcome back! Session diperpanjang',
                    'data' => [
                        'customer_uuid' => $customer->uuid,
                        'session_token' => $customer->session_token,
                        'table' => [
                            'id' => $table->id,
                            'table_number' => $table->table_number,
                            'status' => $table->status
                        ],
                        'session_expires_at' => $activeOrder->session_expires_at,
                        'is_returning_customer' => true,
                        'existing_orders' => $customer->orders()
                            ->where('table_id', $table->id)
                            ->where('created_at', '>=', now()->subHours(3))
                            ->count()
                    ]
                ]);
            }
        }

        // 5) ✅ REMOVED BLOCKING LOGIC - Always allow new scan
        // Multiple customers can have active sessions on same table
        // Tracking is by device_id, not table status

        // 6) Buat / update customer (persist device_id)
        if (!$customer) {
            $customer = Customer::create([
                'uuid'          => (string) Str::uuid(),
                'device_id'     => $deviceId,
                'session_token' => Str::random(32),
                'user_agent'    => $request->userAgent(),
                'ip_address'    => $request->ip(),
                'last_activity' => now()
            ]);
        } else {
            // Update existing customer
            if (!$customer->device_id) {
                $customer->device_id = $deviceId;
            }
            $customer->session_token = Str::random(32);
            $customer->user_agent = $request->userAgent();
            $customer->ip_address = $request->ip();
            $customer->last_activity = now();
            $customer->save();
        }

        // 7) Update table info (informational only, not for blocking)
        $table->update([
            'status' => 'Occupied', // Display purposes only
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR berhasil di-scan',
            'data' => [
                'customer_uuid' => $customer->uuid,
                'session_token' => $customer->session_token,
                'table' => [
                    'id' => $table->id,
                    'table_number' => $table->table_number,
                    'status' => $table->status
                ],
                'session_expires_at' => now()->addHours(2),
                'is_returning_customer' => (bool) $customer->wasRecentlyCreated === false
            ]
        ]);
    }

    public function getSession(Request $request, $token)
    {
        $customer = Customer::where('session_token', $token)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Session tidak ditemukan'
            ], 404);
        }

        $customer->update(['last_activity' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'customer_uuid' => $customer->uuid,
                'session_token' => $customer->session_token,
                'last_activity' => $customer->last_activity,
                'email' => $customer->email
            ]
        ]);
    }

    private function generateDeviceId(array $deviceInfo, Request $request): string
    {
        // Fallback hash (kalau frontend belum kirim device_id)
        $components = [
            $request->userAgent(),
            $request->ip(),
            $deviceInfo['screen_width'] ?? '',
            $deviceInfo['screen_height'] ?? '',
            $deviceInfo['timezone'] ?? ''
        ];
        return md5(implode('|', $components));
    }
}