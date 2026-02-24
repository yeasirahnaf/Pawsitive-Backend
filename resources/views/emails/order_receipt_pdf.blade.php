<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt — {{ $order->order_number }}</title>
    <style>
        body     { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1       { font-size: 18px; color: #4f46e5; }
        .meta    { margin-bottom: 16px; }
        .meta td { padding: 2px 8px 2px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th { background: #f3f4f6; padding: 6px; text-align: left; }
        table.items td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        .total   { font-weight: bold; }
        .footer  { margin-top: 24px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>🐾 Pawsitive — Purchase Receipt</h1>

    <table class="meta">
        <tr><td><strong>Order Number:</strong></td><td>{{ $order->order_number }}</td></tr>
        <tr><td><strong>Date:</strong></td><td>{{ $order->created_at->format('d M Y, H:i') }}</td></tr>
        <tr><td><strong>Customer:</strong></td><td>{{ $order->user?->name ?? $order->guestContact?->name ?? 'Guest' }}</td></tr>
        <tr><td><strong>Payment:</strong></td><td>Cash on Delivery</td></tr>
        <tr><td><strong>Delivery To:</strong></td><td>{{ $order->deliveryAddress->address_line }}, {{ $order->deliveryAddress->city }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>#</th><th>Pet</th><th>Species</th><th>Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->pet_name_snapshot }}</td>
                <td>{{ $item->pet_species_snapshot }}</td>
                <td>৳{{ number_format($item->price_snapshot, 2) }}</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="3">Delivery Fee</td>
                <td>৳{{ number_format($order->delivery_fee, 2) }}</td>
            </tr>
            <tr class="total">
                <td colspan="3">Total</td>
                <td>৳{{ number_format($order->total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">Thank you for shopping with Pawsitive. This is an automated receipt.</div>
</body>
</html>
