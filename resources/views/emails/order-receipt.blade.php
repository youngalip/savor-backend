<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #e74c3c; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 28px; font-weight: bold; color: #e74c3c; margin-bottom: 10px; }
        .receipt-title { font-size: 24px; color: #333; margin: 0; }
        .order-info { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .order-number { font-size: 20px; font-weight: bold; color: #e74c3c; margin-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .info-label { font-weight: bold; color: #666; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { background-color: #e74c3c; color: white; padding: 12px; text-align: left; }
        .items-table td { padding: 12px; border-bottom: 1px solid #ddd; }
        .items-table tr:nth-child(even) { background-color: #f8f9fa; }
        .total-section { background-color: #e74c3c; color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .total-amount { font-size: 28px; font-weight: bold; margin: 0; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
        .status-paid { background-color: #27ae60; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">🍽️ SAVOR</div>
            <h1 class="receipt-title">Payment Receipt</h1>
        </div>

        <!-- Order Info -->
        <div class="order-info">
            <div class="order-number">Order #{{ $order->order_number }}</div>
            
            <div class="info-row">
                <span class="info-label">Date & Time:</span>
                <span>{{ $order->paid_at->format('d M Y, H:i') }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Table:</span>
                <span>{{ $order->table->table_number }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Customer Email:</span>
                <span>{{ $order->customer->email }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Payment Status:</span>
                <span class="status-paid">PAID</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Payment Reference:</span>
                <span>{{ $order->payment_reference }}</span>
            </div>
        </div>

        <!-- Order Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->menu->name }}</strong>
                        @if($item->special_notes)
                            <br><small style="color: #666;">Note: {{ $item->special_notes }}</small>
                        @endif
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Total -->
        <div class="total-section">
            <p style="margin: 0; font-size: 18px;">Total Amount</p>
            <p class="total-amount">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</p>
        </div>

        <!-- Customer Notes -->
        @if($order->notes)
        <div style="margin-top: 20px; padding: 15px; background-color: #fff3cd; border-radius: 8px;">
            <strong>Customer Notes:</strong><br>
            {{ $order->notes }}
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for dining with us! 🙏</strong></p>
            <p>Questions? Contact us at support@savor.com</p>
            <p style="font-size: 12px; color: #999;">
                This is an automated email. Please do not reply to this message.
            </p>
        </div>
    </div>
</body>
</html>