<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;
use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct()
    {
        // Set Midtrans configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    public function createSnapToken(Order $order)
    {
        try {
            // Prepare order items for Midtrans
            $itemDetails = [];
            foreach ($order->items as $item) {
                $itemDetails[] = [
                    'id' => $item->menu->id,
                    'price' => (int) $item->price,
                    'quantity' => $item->quantity,
                    'name' => $item->menu->name,
                    'category' => $item->menu->category->name
                ];
            }

            // Customer details
            $customerDetails = [
                'first_name' => 'Customer',
                'last_name' => '',
                'email' => $order->customer->email,
                'phone' => '08123456789' // Default phone, bisa diambil dari form
            ];

            // Transaction details
            $transactionDetails = [
                'order_id' => $order->order_uuid,
                'gross_amount' => (int) $order->total_amount
            ];

            // Build Snap parameter
            $snapParams = [
                'transaction_details' => $transactionDetails,
                'customer_details' => $customerDetails,
                'item_details' => $itemDetails,
                'callbacks' => [
                    'finish' => url('/payment/finish'),
                    'unfinish' => url('/payment/unfinish'),
                    'error' => url('/payment/error')
                ],
                'custom_field1' => $order->table->table_number,
                'custom_field2' => $order->order_number
            ];

            // Get Snap Token
            $snapToken = Snap::getSnapToken($snapParams);

            // Log payment creation
            PaymentLog::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'transaction_id' => $order->order_uuid,
                'status' => 'Pending',
                'response_data' => [
                    'snap_token' => $snapToken,
                    'snap_params' => $snapParams
                ]
            ]);

            return [
                'success' => true,
                'snap_token' => $snapToken,
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $snapToken
            ];

        } catch (\Exception $e) {
            Log::error('Midtrans Snap Token Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create payment token: ' . $e->getMessage()
            ];
        }
    }

    public function handleNotification($notification)
    {
        try {
            $notif = new Notification();
            
            $transactionStatus = $notif->transaction_status;
            $fraudStatus = $notif->fraud_status;
            $orderId = $notif->order_id;
            $transactionId = $notif->transaction_id;

            // Find order
            $order = Order::where('order_uuid', $orderId)->first();
            
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found'];
            }

            // Handle different transaction status
            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'challenge') {
                    $paymentStatus = 'Pending';
                } else if ($fraudStatus == 'accept') {
                    $paymentStatus = 'Paid';
                }
            } else if ($transactionStatus == 'settlement') {
                $paymentStatus = 'Paid';
            } else if ($transactionStatus == 'pending') {
                $paymentStatus = 'Pending';
            } else if ($transactionStatus == 'deny') {
                $paymentStatus = 'Failed';
            } else if ($transactionStatus == 'expire') {
                $paymentStatus = 'Failed';
            } else if ($transactionStatus == 'cancel') {
                $paymentStatus = 'Failed';
            }

            // Update order status
            $order->update([
                'payment_status' => $paymentStatus,
                'payment_reference' => $transactionId,
                'paid_at' => $paymentStatus === 'Paid' ? now() : null
            ]);

            // Log payment result
            PaymentLog::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'transaction_id' => $transactionId,
                'status' => $paymentStatus === 'Paid' ? 'Success' : ucfirst(strtolower($paymentStatus)),
                'response_data' => $notif->getResponse()
            ]);

            return [
                'success' => true,
                'status' => $paymentStatus,
                'order' => $order
            ];

        } catch (\Exception $e) {
            Log::error('Midtrans Notification Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to process notification: ' . $e->getMessage()
            ];
        }
    }
}