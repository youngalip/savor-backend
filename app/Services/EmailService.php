<?php

namespace App\Services;

use App\Models\Order;
use App\Mail\OrderReceiptMail;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send order confirmation email after order created
     */
    public function sendOrderConfirmation(Order $order)
    {
        try {
            if (!$order->customer->email) {
                Log::warning('Cannot send order confirmation: no email', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            Mail::to($order->customer->email)
                ->send(new OrderConfirmationMail($order));

            Log::info('Order confirmation email sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'email' => $order->customer->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send payment receipt email after successful payment
     */
    public function sendPaymentReceipt(Order $order)
    {
        try {
            if (!$order->customer->email) {
                Log::warning('Cannot send payment receipt: no email', [
                    'order_id' => $order->id
                ]);
                return false;
            }

            if ($order->payment_status !== 'Paid') {
                Log::warning('Cannot send payment receipt: order not paid', [
                    'order_id' => $order->id,
                    'payment_status' => $order->payment_status
                ]);
                return false;
            }

            Mail::to($order->customer->email)
                ->send(new OrderReceiptMail($order));

            Log::info('Payment receipt email sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'email' => $order->customer->email,
                'amount' => $order->total_amount
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment receipt email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}