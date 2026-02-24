<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmed — Pawsitive</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f9f9f9; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #4f46e5; padding: 32px; text-align: center; color: #fff; }
        .header h1 { margin: 0; font-size: 26px; }
        .header p  { margin: 8px 0 0; opacity: .85; }
        .body   { padding: 32px; }
        .order-number { font-size: 20px; font-weight: bold; color: #4f46e5; }
        table   { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th      { background: #f3f4f6; text-align: left; padding: 10px; font-size: 13px; color: #6b7280; }
        td      { padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .total-row td { font-weight: bold; font-size: 15px; border-bottom: none; }
        .address-box { margin-top: 24px; background: #f9fafb; border-radius: 6px; padding: 16px; font-size: 14px; }
        .footer { padding: 24px 32px; background: #f3f4f6; text-align: center; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🐾 Order Confirmed!</h1>
        <p>Thank you for choosing Pawsitive.</p>
    </div>

    <div class="body">
        <p>Hi <strong>{{ $order->user?->name ?? $order->guestContact?->name ?? 'there' }}</strong>,</p>
        <p>We've received your order and it's being processed. Here are your order details:</p>

        <p class="order-number">{{ $order->order_number }}</p>

        <table>
            <thead>
                <tr>
                    <th>Pet</th>
                    <th>Species / Breed</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->pet_name_snapshot }}</td>
                    <td>{{ $item->pet_species_snapshot }}{{ $item->pet_breed_snapshot ? ' / '.$item->pet_breed_snapshot : '' }}</td>
                    <td>৳{{ number_format($item->price_snapshot, 2) }}</td>
                </tr>
                @endforeach

                <tr>
                    <td colspan="2">Delivery Fee</td>
                    <td>৳{{ number_format($order->delivery_fee, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="2">Total</td>
                    <td>৳{{ number_format($order->total, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="address-box">
            <strong>Delivery Address</strong><br>
            {{ $order->deliveryAddress->address_line }}<br>
            @if ($order->deliveryAddress->area) {{ $order->deliveryAddress->area }}, @endif
            {{ $order->deliveryAddress->city }}
        </div>

        <p style="margin-top:24px; font-size:13px; color:#6b7280;">
            Payment Method: <strong>Cash on Delivery</strong><br>
            You can track your order at any time using your order number.
        </p>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} Pawsitive Pet Shop. All rights reserved.
    </div>
</div>
</body>
</html>
