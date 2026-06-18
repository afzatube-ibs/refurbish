@extends('layouts.app')

@section('title', 'Dispatch Batch '.$batch->batch_no.' — DropFlow SFM')
@section('page-title', 'Dispatch Batch '.$batch->batch_no)
@section('page-subtitle', $batch->supplier?->name.' · '.($batch->connection?->store_url ? parse_url($batch->connection->store_url, PHP_URL_HOST) : 'Store'))

@section('page-actions')
    <a href="{{ route('reports.dispatch.print', $batch) }}" target="_blank" rel="noopener"
       class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Print
    </a>
    <a href="{{ route('reports.dispatch') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Back to list
    </a>
@endsection

@section('content')
<div class="dispatch-report-summary grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Dispatch Date</p>
        <p class="dispatch-report-card-value text-lg">{{ $batch->dispatch_date->format('M j, Y') }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Dispatched Orders</p>
        <p class="dispatch-report-card-value text-lg">{{ $batch->total_orders }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Dispatched Qty</p>
        <p class="dispatch-report-card-value text-lg">{{ $batch->total_qty }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Supplier Cost</p>
        <p class="dispatch-report-card-value text-lg tabular-nums">{{ number_format($batch->total_supplier_cost, 2) }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Status</p>
        <p class="dispatch-report-card-value text-lg">{{ $batch->status->label() }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Created</p>
        <p class="dispatch-report-card-value text-sm">{{ $batch->created_at?->format('M j, Y g:i A') }}</p>
        <p class="text-xs text-slate-500 mt-1">by {{ $batch->creator?->name ?? '—' }}</p>
    </div>
</div>

<div class="dispatch-report-table-wrap bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
        <h2 class="font-medium text-slate-900">Dispatched Orders &amp; Products</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Order No</th>
                    <th class="text-left font-medium text-slate-600">Customer</th>
                    <th class="text-left font-medium text-slate-600">Phone</th>
                    <th class="text-left font-medium text-slate-600">Product</th>
                    <th class="text-left font-medium text-slate-600">Model / IBS</th>
                    <th class="text-right font-medium text-slate-600">Qty</th>
                    <th class="text-right font-medium text-slate-600">Unit Cost</th>
                    <th class="text-right font-medium text-slate-600">Line Cost</th>
                    <th class="text-left font-medium text-slate-600">Courier</th>
                    <th class="text-left font-medium text-slate-600">Consignment</th>
                    <th class="text-left font-medium text-slate-600">Cost Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orderRows as $row)
                    @php $batchOrder = $row['order']; $items = $row['items']; @endphp
                    @foreach ($items as $index => $item)
                        <tr class="hover:bg-slate-50">
                            @if ($index === 0)
                                <td class="font-medium text-slate-900" rowspan="{{ $items->count() }}">#{{ $batchOrder->order_no }}</td>
                                <td class="text-slate-700" rowspan="{{ $items->count() }}">{{ $batchOrder->customer_name }}</td>
                                <td class="text-slate-600" rowspan="{{ $items->count() }}">{{ $batchOrder->phone }}</td>
                            @endif
                            <td class="text-slate-700">{{ $item->product_name }}</td>
                            <td class="text-slate-600 text-xs font-mono">
                                {{ $item->model }}@if ($item->ibs_model) / {{ $item->ibs_model }}@endif
                            </td>
                            <td class="text-right tabular-nums">{{ $item->qty }}</td>
                            <td class="text-right tabular-nums">{{ number_format($item->supplier_unit_cost, 2) }}</td>
                            <td class="text-right tabular-nums font-medium">{{ number_format($item->supplier_total_cost, 2) }}</td>
                            @if ($index === 0)
                                <td class="text-slate-600" rowspan="{{ $items->count() }}">{{ $batchOrder->courier ?: '—' }}</td>
                                <td class="text-slate-600 font-mono text-xs" rowspan="{{ $items->count() }}">{{ $batchOrder->consignment_id }}</td>
                            @endif
                            <td class="text-slate-600">
                                @if ($item->cost_status->value === 'missing_cost')
                                    <span class="inline-flex rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 border border-amber-200">Missing cost</span>
                                @else
                                    <span class="text-slate-500 text-xs">OK</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-slate-500 py-12">No line items in this batch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
