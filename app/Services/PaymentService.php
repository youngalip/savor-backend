<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;
use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    protected $emailService;
    protected $stationAssignmentService;

    public function __construct(StationAssignmentService $stationAssignmentService)
    {
        // Set Midtrans configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
        
        $this->emailService = new EmailService();
        $this->stationAssignmentService = $stationAssignmentService;
    }

    public function createSnapToken(Order $order)
    {
        try {
            $backendUrl = config('app.url');
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

            // item_details
            $itemDetails = [];
            foreach ($order->items as $item) {
                $itemDetails[] = [
                    'id'       => 'ITEM-' . $item->menu->id,
                    'price'    => (int) $item->price,
                    'quantity' => $item->quantity,
                    'name'     => $item->menu->name,
                    'category' => $item->menu->category->name ?? 'Food & Beverage'
                ];
            }
            if ($order->service_charge_amount > 0) {
                $itemDetails[] = [
                    'id' => 'SERVICE-CHARGE',
                    'price' => (int) $order->service_charge_amount,
                    'quantity' => 1,
                    'name' => 'Service Charge (' . round($order->service_charge_rate * 100) . '%)'
                ];
            }
            if ($order->tax_amount > 0) {
                $itemDetails[] = [
                    'id' => 'TAX',
                    'price' => (int) $order->tax_amount,
                    'quantity' => 1,
                    'name' => 'Restaurant Tax (' . round($order->tax_rate * 100) . '%)'
                ];
            }

            $calculatedTotal = array_reduce($itemDetails, fn($sum, $i) => $sum + ($i['price'] * $i['quantity']), 0);
            if ($calculatedTotal != (int) $order->total_amount) {
                Log::warning('Midtrans amount mismatch', [
                    'calculated'  => $calculatedTotal,
                    'order_total' => (int) $order->total_amount,
                    'item_details'=> $itemDetails
                ]);
            }

            $customerDetails = [
                'first_name' => 'Customer',
                'last_name'  => '',
                'email'      => $order->customer->email,
                'phone'      => '08123456789'
            ];

            $transactionDetails = [
                'order_id'     => $order->order_uuid,
                'gross_amount' => (int) $order->total_amount
            ];

            $snapParams = [
                'transaction_details' => $transactionDetails,
                'customer_details'    => $customerDetails,
                'item_details'        => $itemDetails,
                'callbacks' => [
                    'finish'   => $frontendUrl . '/payment-success?order_id=' . $order->order_uuid,
                    'unfinish' => $frontendUrl . '/payment-pending?order_id=' . $order->order_uuid,
                    'error'    => $frontendUrl . '/payment-error?order_id=' . $order->order_uuid
                ],
                'custom_field1' => $order->table->table_number,
                'custom_field2' => $order->order_number
            ];

            Log::info('Creating Midtrans Snap Token', [
                'order_uuid' => $order->order_uuid,
                'backend_url' => $backendUrl,
                'frontend_url'=> $frontendUrl
            ]);

            $snapToken = Snap::getSnapToken($snapParams);

            PaymentLog::create([
                'order_id'       => $order->id,
                'amount'         => $order->total_amount,
                'transaction_id' => $order->order_uuid,
                'status'         => 'Pending',
                'response_data'  => [
                    'snap_token' => $snapToken
                ]
            ]);

            return [
                'success'      => true,
                'snap_token'   => $snapToken,
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $snapToken
            ];

        } catch (\Exception $e) {
            Log::error('Midtrans Snap Token Error: ' . $e->getMessage(), [
                'order_uuid' => $order->order_uuid ?? null,
                'trace'      => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create payment token: ' . $e->getMessage()
            ];
        }
    }

    public function handleNotification($notification)
    {
        try {
            // 📋 Log awal untuk memastikan callback diterima
            Log::info('✅ Midtrans callback received', [
                'raw_notification' => $notification
            ]);

            // 🔹 Ambil field utama dari notifikasi Midtrans
            $transactionStatus = $notification['transaction_status'] ?? null;
            $fraudStatus = $notification['fraud_status'] ?? 'accept';
            $orderId = $notification['order_id'] ?? null;
            $transactionId = $notification['transaction_id'] ?? null;

            // 🔍 Validasi data penting
            if (!$orderId || !$transactionStatus) {
                Log::error('❌ Missing required fields', [
                    'order_id' => $orderId,
                    'transaction_status' => $transactionStatus
                ]);
                return [
                    'success' => false,
                    'message' => 'Missing required fields: order_id or transaction_status'
                ];
            }

            // 🔍 Cari order berdasarkan UUID dengan relasi yang diperlukan
            $order = Order::with('items.menu.category')
                ->where('order_uuid', $orderId)
                ->first();
                
            if (!$order) {
                Log::error('❌ Order not found', ['order_id' => $orderId]);
                return ['success' => false, 'message' => 'Order not found'];
            }

            Log::info('🧩 Order found', [
                'order_uuid' => $order->order_uuid,
                'current_status' => $order->payment_status
            ]);

            // 🔄 Tentukan status baru berdasarkan status transaksi
            $paymentStatus = match ($transactionStatus) {
                'capture' => ($fraudStatus === 'accept') ? 'Paid' : 'Pending',
                'settlement' => 'Paid',
                'pending' => 'Pending',
                'deny', 'expire', 'cancel' => 'Failed',
                default => 'Pending',
            };

            Log::info('📦 Determined payment status', [
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus,
                'new_status' => $paymentStatus
            ]);

            // 💾 Update status order dan assign ke station dalam transaction
            DB::transaction(function () use ($order, $paymentStatus, $transactionId) {
                // Update order payment status
                $order->update([
                    'payment_status' => $paymentStatus,
                    'payment_reference' => $transactionId,
                    'paid_at' => $paymentStatus === 'Paid' ? now() : null
                ]);

                // 🍳 ASSIGN KE STATION (Kitchen/Bar/Pastry) jika pembayaran sukses
                if ($paymentStatus === 'Paid') {
                    try {
                        $this->stationAssignmentService->assignOrderToStations($order);
                        
                        Log::info('🍽️ Order assigned to stations', [
                            'order_uuid' => $order->order_uuid,
                            'order_number' => $order->order_number,
                            'stations_count' => $order->stationOrders()->count()
                        ]);
                    } catch (\Exception $e) {
                        Log::error('❌ Failed to assign order to stations', [
                            'order_uuid' => $order->order_uuid,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        // Tidak throw exception karena pembayaran sudah sukses
                        // Station assignment bisa di-retry manual atau lewat cron job
                    }
                }
            });

            // 🧠 Pastikan perubahan tersimpan
            $freshOrder = $order->fresh();

            Log::info('✅ Order updated successfully', [
                'order_uuid' => $freshOrder->order_uuid,
                'payment_status' => $freshOrder->payment_status,
                'station_orders_count' => $freshOrder->stationOrders()->count()
            ]);

            // 💌 Kirim email receipt (jika sukses bayar)
            if ($paymentStatus === 'Paid') {
                try {
                    $this->emailService->sendPaymentReceipt(
                        $freshOrder->load(['customer', 'table', 'items.menu'])
                    );
                    Log::info('📧 Payment receipt email sent successfully');
                } catch (\Exception $e) {
                    Log::warning('⚠️ Failed to send payment receipt: ' . $e->getMessage());
                }
            }

            // 🪵 Simpan log pembayaran
            $statusEnum = match (strtolower($paymentStatus)) {
                'paid' => 'Success',
                'failed' => 'Failed',
                default => 'Pending',
            };

            // 🔍 Cek apakah sudah ada log sebelumnya
            $existingLog = PaymentLog::where('order_id', $order->id)
                ->whereIn('status', ['Pending', 'Failed'])
                ->latest()
                ->first();

            if ($existingLog) {
                // 🔄 Update log lama jadi status terbaru
                $existingLog->update([
                    'status' => $statusEnum,
                    'transaction_id' => $transactionId,
                    'response_data' => $notification
                ]);

                Log::info('📝 Payment log updated', [
                    'order_id' => $order->id,
                    'old_status' => $existingLog->getOriginal('status'),
                    'new_status' => $statusEnum
                ]);
            } else {
                // 🆕 Kalau belum ada log sama sekali, buat baru
                PaymentLog::create([
                    'order_id' => $order->id,
                    'amount' => $order->total_amount,
                    'transaction_id' => $transactionId,
                    'status' => $statusEnum,
                    'response_data' => $notification
                ]);

                Log::info('💾 Payment log created (no existing log found)', [
                    'order_id' => $order->id,
                    'status' => $statusEnum
                ]);
            }

            return [
                'success' => true,
                'status' => $paymentStatus,
                'order' => $freshOrder,
                'assigned_to_stations' => $paymentStatus === 'Paid'
            ];

        } catch (\Exception $e) {
            Log::error('💥 handleNotification Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'notification' => $notification
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Manual retry untuk assign order ke station
     * Berguna jika auto-assign gagal saat callback
     */
    public function retryStationAssignment(Order $order): array
    {
        try {
            // Validasi order sudah dibayar
            if (!$order->isPaid()) {
                return [
                    'success' => false,
                    'message' => 'Order belum dibayar'
                ];
            }

            // Check apakah sudah di-assign sebelumnya
            if ($order->stationOrders()->exists()) {
                return [
                    'success' => false,
                    'message' => 'Order sudah di-assign ke station sebelumnya'
                ];
            }

            // Load relasi yang diperlukan
            $order->load('items.menu.category');

            // Assign ke station
            $this->stationAssignmentService->assignOrderToStations($order);

            Log::info('✅ Manual station assignment successful', [
                'order_uuid' => $order->order_uuid,
                'stations_count' => $order->stationOrders()->count()
            ]);

            return [
                'success' => true,
                'message' => 'Order berhasil di-assign ke station',
                'stations' => $order->getStationOrdersByType()
            ];

        } catch (\Exception $e) {
            Log::error('❌ Manual station assignment failed', [
                'order_uuid' => $order->order_uuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Gagal assign ke station: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get order status dengan informasi station
     */
    public function getOrderStatusWithStations(Order $order): array
    {
        $order->load(['stationOrders.orderItem.menu', 'items.menu']);

        return [
            'order_uuid' => $order->order_uuid,
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'paid_at' => $order->paid_at,
            'total_amount' => $order->total_amount,
            'preparation_progress' => $order->getPreparationProgress(),
            'station_orders' => $order->getStationOrdersByType(),
            'is_all_stations_completed' => $order->isAllStationsCompleted()
        ];
    }
}