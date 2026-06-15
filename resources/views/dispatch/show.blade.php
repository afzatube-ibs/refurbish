@extends('layouts.app')

@section('title', 'Dispatch Report #' . $report->id . ' — DropFlow SFM')
@section('page-title', 'Dispatch Report')
@section('page-subtitle', 'Order #' . ($report->order?->source_order_id ?? '—') . ' · ' . $report->dispatch_date->format('M j, Y'))

@section('content')
<div class="mb-4">
    <a href="{{ route('dispatch-report.index') }}" class="text-sm text-slate-600 hover:text-slate-900 underline">← Back to dispatch reports</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 class="font-medium text-slate-900">Dispatched Items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left font-medium text-slate-600">Product</th>
                            <th class="text-left font-medium text-slate-600">Model</th>
                            <th class="text-right font-medium text-slate-600">Qty</th>
                            <th class="text-right font-medium text-slate-600">Unit Cost</th>
                            <th class="text-right font-medium text-slate-600">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php $grandTotal = 0; @endphp
                        @foreach ($report->items as $item)
                            @php
                                $lineTotal = $item->quantity * $item->supplier_cost_snapshot;
                                $grandTotal += $lineTotal;
                            @endphp
                            <tr>
                                <td class="font-medium text-slate-900">{{ $item->orderItem?->product_name ?? '—' }}</td>
                                <td class="text-slate-600">{{ $item->orderItem?->model ?? '—' }}</td>
                                <td class="text-right text-slate-900">{{ $item->quantity }}</td>
                                <td class="text-right text-slate-600">{{ number_format($item->supplier_cost_snapshot, 2) }}</td>
                                <td class="text-right font-medium text-slate-900">{{ number_format($lineTotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="4" class="text-right font-medium text-slate-600">Total supplier cost</td>
                            <td class="text-right font-semibold text-slate-900">{{ number_format($grandTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-lg border border-slate-200 p-6 text-sm">
            <h3 class="font-medium text-slate-900 mb-4">Dispatch Details</h3>
            <dl class="space-y-3 text-slate-600">
                <div>
                    <dt class="text-xs text-slate-500">Order</dt>
                    <dd class="font-medium text-slate-900 mt-0.5">
                        @if ($report->order)
                            <a href="{{ route('orders.show', $report->order) }}" class="hover:underline">#{{ $report->order->source_order_id }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Supplier</dt>
                    <dd class="font-medium text-slate-900 mt-0.5">{{ $report->supplier?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Dispatch Date</dt>
                    <dd class="font-medium text-slate-900 mt-0.5">{{ $report->dispatch_date->format('M j, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Courier</dt>
                    <dd class="font-medium text-slate-900 mt-0.5">{{ $report->courier }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Consignment ID</dt>
                    <dd class="font-medium text-slate-900 mt-0.5 font-mono">{{ $report->consignment_id }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Created By</dt>
                    <dd class="font-medium text-slate-900 mt-0.5">{{ $report->creator?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Recorded At</dt>
                    <dd class="font-medium text-slate-900 mt-0.5">{{ $report->created_at->format('M j, Y H:i') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
