<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function process(Request $request)
    {
        $request->validate([
            'order_uuid' => 'required|exists:orders,order_uuid'
        ]);

        try {
            $order = Order::with(['items.menu.category', 'customer', 'table'])
                          ->where('order_uuid', $request->order_uuid)
                          ->first();

            if ($order->payment_status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order sudah diproses atau tidak valid'
                ], 400);
            }

            // Create Midtrans Snap Token
            $result = $this->paymentService->createSnapToken($order);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment token berhasil dibuat',
                'data' => [
                    'snap_token' => $result['snap_token'],
                    'redirect_url' => $result['redirect_url'],
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'customer_email' => $order->customer->email,
                    'table_number' => $order->table->table_number
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Process Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembayaran'
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        try {
            $result = $this->paymentService->handleNotification($request->all());

            if ($result['success']) {
                Log::info('Payment notification processed', [
                    'order_id' => $result['order']->order_uuid,
                    'status' => $result['status']
                ]);

                return response()->json(['status' => 'OK']);
            }

            return response()->json(['status' => 'FAILED'], 400);

        } catch (\Exception $e) {
            Log::error('Payment Callback Error: ' . $e->getMessage());
            return response()->json(['status' => 'ERROR'], 500);
        }
    }

    public function finish(Request $request)
    {
        // Redirect page setelah payment selesai
        $orderUuid = $request->get('order_id');
        $transactionStatus = $request->get('transaction_status');

        return response()->json([
            'message' => 'Payment completed',
            'order_id' => $orderUuid,
            'status' => $transactionStatus
        ]);
    }
}