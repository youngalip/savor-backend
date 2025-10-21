<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QRController extends Controller
{
    public function scanQR(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
            'device_info' => 'array'
        ]);

        try {
            // 1. Cari table berdasarkan QR code
            $table = Table::where('qr_code', $request->qr_code)->first();
            
            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code tidak valid'
                ], 404);
            }

            // 2. Generate device ID
            $deviceInfo = $request->device_info ?? [];
            $deviceId = $this->generateDeviceId($deviceInfo, $request);

            // 3. Cek apakah customer ini sudah pernah scan QR meja ini sebelumnya
            $existingCustomer = Customer::where('device_id', $deviceId)->first();

            if ($existingCustomer) {
                // Customer yang sama - cek apakah ada session aktif
                $activeOrder = Order::where('customer_id', $existingCustomer->id)
                                    ->where('table_id', $table->id)
                                    ->where('session_expires_at', '>', now())
                                    ->first();

                if ($activeOrder) {
                    // Customer yang sama dengan session aktif - ALLOW (extend session)
                    $existingCustomer->update([
                        'session_token' => Str::random(32),
                        'last_activity' => now()
                    ]);

                    // Extend session time
                    $activeOrder->update([
                        'session_expires_at' => now()->addHours(2)
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Welcome back! Session diperpanjang',
                        'data' => [
                            'customer_uuid' => $existingCustomer->uuid,
                            'session_token' => $existingCustomer->session_token,
                            'table' => [
                                'id' => $table->id,
                                'table_number' => $table->table_number,
                                'status' => $table->status
                            ],
                            'session_expires_at' => $activeOrder->session_expires_at,
                            'is_returning_customer' => true,
                            'existing_orders' => $existingCustomer->orders()
                                                                ->where('table_id', $table->id)
                                                                ->where('created_at', '>=', now()->subHours(3))
                                                                ->count()
                        ]
                    ]);
                }
            }

            // 4. Cek apakah ada customer LAIN yang menggunakan meja ini
            if ($table->status === 'Occupied') {
                $activeOrderByOthers = Order::where('table_id', $table->id)
                                        ->where('session_expires_at', '>', now())
                                        ->where('payment_status', 'Pending')
                                        ->whereHas('customer', function($query) use ($deviceId) {
                                            $query->where('device_id', '!=', $deviceId);
                                        })
                                        ->first();
                
                if ($activeOrderByOthers) {
                    // Ada customer LAIN yang masih aktif - REJECT
                    return response()->json([
                        'success' => false,
                        'message' => 'Meja sedang digunakan customer lain',
                        'data' => [
                            'table_status' => 'Occupied',
                            'estimated_free_at' => $activeOrderByOthers->session_expires_at,
                            'alternative_action' => 'Silakan tunggu atau pilih meja lain'
                        ]
                    ], 409);
                } else {
                    // Tidak ada customer aktif, reset table
                    $table->update(['status' => 'Free']);
                }
            }

            // 5. Create atau update customer
            if (!$existingCustomer) {
                $customer = Customer::create([
                    'uuid' => (string) Str::uuid(),
                    'device_id' => $deviceId,
                    'session_token' => Str::random(32),
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'last_activity' => now()
                ]);
            } else {
                $customer = $existingCustomer;
                $customer->update([
                    'session_token' => Str::random(32),
                    'last_activity' => now()
                ]);
            }

            // 6. Set table status
            $table->update(['status' => 'Occupied']);

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
                    'is_returning_customer' => $existingCustomer ? true : false
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
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

        // Update last activity
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

    private function generateDeviceId($deviceInfo, $request)
    {
        // Device ID sederhana berdasarkan user agent + IP + screen info
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