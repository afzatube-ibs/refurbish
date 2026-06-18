<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispatch Batch {{ $batch->batch_no }} — Print</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #0f172a; margin: 24px; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #475569; margin-bottom: 16px; }
        .summary { display: flex; gap: 24px; margin-bottom: 20px; flex-wrap: wrap; }
        .summary div { min-width: 120px; }
        .summary dt { color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        .summary dd { margin: 2px 0 0; font-weight: 600; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .missing { color: #b45309; font-weight: 600; }
        @media print { body { margin: 12mm; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <h1>Dispatch Batch {{ $batch->batch_no }}</h1>
    <p class="meta">
        {{ $batch->supplier?->name }}
        · {{ $batch->connection?->store_url ? parse_url($batch->connection->store_url, PHP_URL_HOST) : 'Store' }}
        · {{ $batch->dispatch_date->format('d M Y') }}
        · {{ $batch->status->label() }}
    </p>

    <dl class="summary">
        <div><dt>Dispatched Orders</dt><dd>{{ $batch->total_orders }}</dd></div>
        <div><dt>Dispatched Qty</dt><dd>{{ $batch->total_qty }}</dd></div>
        <div><dt>Supplier Cost</dt><dd>{{ number_format($batch->total_supplier_cost, 2) }}</dd></div>
    </dl>

    <table>
        <thead>
            <tr>
                <th>Order No</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Product</th>
                <th>Model / IBS</th>
                <th class="num">Qty</th>
                <th class="num">Unit Cost</th>
                <th class="num">Line Cost</th>
                <th>Courier</th>
                <th>Consignment</th>
                <th>Cost Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineRows as $row)
                <tr>
                    <td>#{{ $row['order_no'] }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    <td>{{ $row['phone'] }}</td>
                    <td>{{ $row['product_name'] }}</td>
                    <td>{{ $row['model'] }}@if ($row['ibs_model']) / {{ $row['ibs_model'] }}@endif</td>
                    <td class="num">{{ $row['qty'] }}</td>
                    <td class="num">{{ number_format($row['supplier_unit_cost'], 2) }}</td>
                    <td class="num">{{ number_format($row['supplier_total_cost'], 2) }}</td>
                    <td>{{ $row['courier'] ?: '—' }}</td>
                    <td>{{ $row['consignment_id'] }}</td>
                    <td class="{{ $row['cost_status']->value === 'missing_cost' ? 'missing' : '' }}">
                        {{ $row['cost_status']->label() }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
