<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Packing Invoice — Order #{{ $order->source_order_id }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        @media print {
            @page {
                margin: 12mm;
                size: A4;
            }

            body {
                background: white;
                padding: 0;
            }

            .order-map-invoice-no-print {
                display: none !important;
            }

            .order-map-invoice-sheet {
                border: none;
                box-shadow: none;
                max-width: none;
                padding: 0;
            }
        }
    </style>
</head>
<body class="order-map-invoice-page">
    <div class="order-map-invoice-toolbar order-map-invoice-no-print">
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print</button>
    </div>

    <main class="order-map-invoice-sheet">
        <header class="order-map-invoice-header">
            <div class="order-map-invoice-brand">
                <p class="order-map-invoice-tag">Packing Invoice</p>
                <h1 class="order-map-invoice-title">Order #{{ $order->source_order_id }}</h1>
                <p class="order-map-invoice-meta">
                    @if ($order->oc_created_at)
                        Order date: {{ $order->oc_created_at->format('M j, Y') }}
                    @endif
                    @if ($order->sfm_status)
                        · {{ $order->sfm_status->label() }}
                    @endif
                </p>
            </div>
            <div class="order-map-invoice-consignment">
                <dl>
                    <dt>Consignment ID</dt>
                    <dd>{{ $order->consignment_id ?: '—' }}</dd>
                    @if ($order->courier_name)
                        <dt>Courier</dt>
                        <dd>{{ $order->courier_name }}</dd>
                    @endif
                </dl>
            </div>
        </header>

        <section class="order-map-invoice-ship-to">
            <h2>Ship To</h2>
            <p class="order-map-invoice-customer-name">{{ $order->customer_name }}</p>
            <p class="order-map-invoice-customer-phone">{{ $order->customer_phone }}</p>
            @if ($order->customer_address)
                <p class="order-map-invoice-customer-address">{{ $order->customer_address }}</p>
            @endif
        </section>

        <section class="order-map-invoice-lines">
            <h2>Items</h2>
            <table class="order-map-invoice-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Model / Variant</th>
                        <th class="order-map-invoice-num">Qty</th>
                        <th class="order-map-invoice-num">Unit Price</th>
                        <th class="order-map-invoice-num">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                <span class="order-map-invoice-product-name">{{ $item->product_name }}</span>
                                @if ($item->is_unmatched)
                                    <span class="order-map-invoice-unmatched">Unmatched</span>
                                @endif
                            </td>
                            <td>{{ $item->variant_label ?: ($item->model ?: '—') }}</td>
                            <td class="order-map-invoice-num">{{ $item->quantity }}</td>
                            <td class="order-map-invoice-num">{{ number_format((float) $item->sale_price, 2) }}</td>
                            <td class="order-map-invoice-num">{{ number_format((float) $item->sale_price * $item->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="order-map-invoice-num">{{ $order->items->sum('quantity') }}</th>
                        <th></th>
                        <th class="order-map-invoice-num">{{ number_format((float) $order->sale_amount, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </section>

        @if ($order->consignment_id)
            <footer class="order-map-invoice-footer">
                <p><strong>Consignment:</strong> {{ $order->consignment_id }}</p>
            </footer>
        @endif
    </main>
</body>
</html>
