<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Customer Invoice Placeholder — Order #{{ $order->source_order_id }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="order-map-invoice-placeholder">
    <main class="order-map-invoice-sheet">
        <p class="order-map-invoice-tag">Customer packing invoice — placeholder</p>
        <h1>Order #{{ $order->source_order_id }}</h1>
        <p>{{ $order->customer_name }} · {{ $order->customer_phone }}</p>
        <ul>
            @foreach ($order->items as $item)
                <li>{{ $item->product_name }} ({{ $item->model }}) × {{ $item->quantity }}</li>
            @endforeach
        </ul>
        <p class="order-map-invoice-note">Full Lokkisona customer invoice format will be added in a later release.</p>
    </main>
</body>
</html>
