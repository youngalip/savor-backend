<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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

            // 2. Generate device ID sederhana
            $deviceInfo = $request->device_info ?? [];
            $deviceId = $this->generateDeviceId($deviceInfo, $request);

            // 3. Cari atau buat customer
            $customer = Customer::where('device_id', $deviceId)->first();
            
            if (!$customer) {
                $customer = Customer::create([
                    'uuid' => (string) Str::uuid(),
                    'device_id' => $deviceId,
                    'session_token' => Str::random(32),
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'last_activity' => now()
                ]);
            } else {
                // Update last activity dan generate session token baru
                $customer->update([
                    'session_token' => Str::random(32),
                    'last_activity' => now()
                ]);
            }

            // 4. Update table status jadi Occupied
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
                    'session_expires_at' => now()->addHours(2)->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem'
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