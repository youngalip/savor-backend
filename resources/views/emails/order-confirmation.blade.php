<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #3498db; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 28px; font-weight: bold; color: #3498db; margin-bottom: 10px; }
        .confirmation-title { font-size: 24px; color: #333; margin: 0; }
        .status-box { background-color: #3498db; color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 30px; }
        .order-info { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .estimated-time { background-color: #f39c12; color: white; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .info-label { font-weight: bold; color: #666; }
        .items-list { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .item { padding: 10px 0; border-bottom: 1px solid #ddd; }
        .item:last-child { border-bottom: none; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">🍽️ SAVOR</div>
            <h1 class="confirmation-title">Order Confirmation</h1>
        </div>

        <!-- Status -->
        <div class="status-box">
            <h2 style="margin: 0; font-size: 20px;">✅ Order Confirmed!</h2>
            <p style="margin: 10px 0 0 0;">Your order has been received and is being prepared</p>
        </div>

        <!-- Estimated Time -->
        <div class="estimated-time">
            <h3 style="margin: 0;">⏰ Estimated Preparation Time</h3>
            <p style="margin: 5px 0 0 0; font-size: 18px; font-weight: bold;">15-20 minutes</p>
        </div>

        <!-- Order Info -->
        <div class="order-info">
            <h3 style="margin-top: 0; color: #3498db;">Order Details</h3>
            
            <div class="info-row">
                <span class="info-label">Order Number:</span>
                <span><strong>{{ $order->order_number }}</strong></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Table:</span>
                <span>{{ $order->table->table_number }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Order Time:</span>
                <span>{{ $order->created_at->format('d M Y, H:i') }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Total Amount:</span>
                <span><strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong></span>
            </div>
        </div>

        <!-- Order Items -->
        <div class="items-list">
            <h3 style="margin-top: 0; color: #3498db;">Your Order</h3>
            
            @foreach($order->items as $item)
            <div class="item">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>{{ $item->menu->name }}</strong>
                        <span style="color: #666;">({{ $item->quantity }}x)</span>
                        @if($item->special_notes)
                            <br><small style="color: #f39c12;">📝 {{ $item->special_notes }}</small>
                        @endif
                    </div>
                    <div style="font-weight: bold;">
                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- What's Next -->
        <div style="background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #27ae60;">What happens next?</h3>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li>Your order is being prepared by our kitchen team</li>
                <li>We'll update you on the progress</li>
                <li>Food will be served directly to your table</li>
                <li>Enjoy your meal! 🍽️</li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for choosing Savor! 🙏</strong></p>
            <p>Need help? Contact our staff or email support@savor.com</p>
            <p style="font-size: 12px; color: #999;">
                This is an automated email. Please do not reply to this message.
            </p>
        </div>
    </div>
</body>
</html>