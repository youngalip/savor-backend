<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Customer;
use App\Models\Order;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QRController extends Controller
{
    private QRCodeService $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    public function scanQR(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
            'device_info' => 'array',
            'device_id' => 'nullable|string'
        ]);

        try {
            // Parse QR code value: QR_{TABLE_NUMBER}_{TIMESTAMP}
            $qrParsed = $this->qrCodeService->parseQRValue($request->qr_code);
            
            // Find table by table_number (not by qr_code field!)
            $table = Table::where('table_number', $qrParsed['table_number'])->first();
            
            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code tidak valid - table tidak ditemukan'
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code format tidak valid: ' . $e->getMessage()
            ], 400);
        }

        // Get device_id
        $deviceId = $request->header('X-Device-Id') 
            ?: ($request->input('device_id') ?: $this->generateDeviceId($request->device_info ?? [], $request));

        // Find customer by device_id
        $customer = Customer::where('device_id', $deviceId)->first();

        // Check for active order from same customer at same table
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

                // Extend session
                $activeOrder->update([
                    'session_expires_at' => now()->addHours(2)
                ]);

                // Update table status (informational only)
                $table->update([
                    'status' => 'Occupied',
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

        // Create or update customer
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
            if (!$customer->device_id) {
                $customer->device_id = $deviceId;
            }
            $customer->session_token = Str::random(32);
            $customer->user_agent = $request->userAgent();
            $customer->ip_address = $request->ip();
            $customer->last_activity = now();
            $customer->save();
        }

        // Update table status (informational only)
        $table->update([
            'status' => 'Occupied',
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

    /**
     * Validate if session is still active
     */
    public function validateSession(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string'
        ]);

        $customer = Customer::where('session_token', $request->session_token)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }

        // Check if session has expired (inactive for more than 2 hours)
        $isExpired = $customer->last_activity->addHours(2)->isPast();

        return response()->json([
            'success' => true,
            'data' => [
                'is_valid' => !$isExpired,
                'customer_uuid' => $customer->uuid,
                'last_activity' => $customer->last_activity
            ]
        ]);
    }

    /**
     * Extend session expiry
     */
    public function extendSession(Request $request, $token)
    {
        $customer = Customer::where('session_token', $token)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }

        $customer->update(['last_activity' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Session extended',
            'data' => [
                'customer_uuid' => $customer->uuid,
                'last_activity' => $customer->last_activity,
                'expires_at' => now()->addHours(2)
            ]
        ]);
    }

    private function generateDeviceId(array $deviceInfo, Request $request): string
    {
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