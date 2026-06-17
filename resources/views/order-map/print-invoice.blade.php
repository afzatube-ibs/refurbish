@php
    $presenter = app(\App\Services\OrderMap\PackingInvoicePresenter::class);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing Invoice — Order #{{ $invoice['order_number'] }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="order-map-invoice-page">
    <div class="order-map-invoice-toolbar order-map-invoice-no-print">
        <a href="{{ route('order-map.index') }}" class="btn btn-secondary btn-sm">Back to Order Queue</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print</button>
    </div>

    <main class="order-map-invoice-sheet">
        <header class="order-map-invoice-header">
            <div class="order-map-invoice-brand">
                <p class="order-map-invoice-business">{{ $invoice['business_name'] }}</p>
                <p class="order-map-invoice-business-sub">{{ $invoice['business_line'] }}</p>
                <p class="order-map-invoice-tag">Packing Invoice</p>
                <h1 class="order-map-invoice-title">Order #{{ $invoice['order_number'] }}</h1>
            </div>
            <div class="order-map-invoice-meta-block">
                <dl class="order-map-invoice-meta-list">
                    <div>
                        <dt>Order date</dt>
                        <dd>{{ $presenter->formatDate($invoice['order_date']) }}</dd>
                    </div>
                    <div>
                        <dt>IBS status</dt>
                        <dd>{{ $invoice['ibs_status'] }}</dd>
                    </div>
                    @if ($invoice['consignment_id'])
                        <div>
                            <dt>Consignment ID</dt>
                            <dd>{{ $invoice['consignment_id'] }}</dd>
                        </div>
                    @endif
                    @if ($invoice['courier_name'])
                        <div>
                            <dt>Courier</dt>
                            <dd>{{ $invoice['courier_name'] }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </header>

        <section class="order-map-invoice-ship-to">
            <h2>Customer</h2>
            <p class="order-map-invoice-customer-name">{{ $invoice['customer_name'] }}</p>
            <p class="order-map-invoice-customer-phone">{{ $invoice['customer_phone'] }}</p>
            @if ($invoice['customer_address'] !== '')
                <p class="order-map-invoice-customer-address">{{ $invoice['customer_address'] }}</p>
            @endif
        </section>

        <section class="order-map-invoice-lines">
            <h2>Products</h2>
            <table class="order-map-invoice-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Model</th>
                        <th>Option</th>
                        <th class="order-map-invoice-num">Qty</th>
                        <th class="order-map-invoice-num">Unit</th>
                        <th class="order-map-invoice-num">Line</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice['line_items'] as $line)
                        <tr>
                            <td><span class="order-map-invoice-product-name">{{ $line['product_name'] }}</span></td>
                            <td class="order-map-invoice-mono">{{ $line['model'] }}</td>
                            <td>{{ $line['option'] }}</td>
                            <td class="order-map-invoice-num">{{ $line['quantity'] }}</td>
                            <td class="order-map-invoice-num">{{ $presenter->formatMoney($line['unit_price']) }}</td>
                            <td class="order-map-invoice-num">{{ $presenter->formatMoney($line['line_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Totals</th>
                        <th class="order-map-invoice-num">{{ $invoice['total_qty'] }}</th>
                        <th></th>
                        <th class="order-map-invoice-num">
                            @if ($invoice['cod_amount'] !== null)
                                {{ $presenter->formatMoney($invoice['cod_amount']) }}
                            @else
                                —
                            @endif
                        </th>
                    </tr>
                </tfoot>
            </table>
            @if ($invoice['cod_amount'] !== null)
                <p class="order-map-invoice-cod-note">COD / order amount: <strong>{{ $presenter->formatMoney($invoice['cod_amount']) }}</strong></p>
            @endif
        </section>

        <footer class="order-map-invoice-footer">
            <p>Printed {{ $presenter->formatDateTime($invoice['print_at']) }}</p>
            @if ($invoice['consignment_id'])
                <p>Consignment ID: <strong>{{ $invoice['consignment_id'] }}</strong></p>
            @endif
        </footer>
    </main>
</body>
</html>
