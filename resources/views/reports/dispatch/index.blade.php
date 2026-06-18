@extends('layouts.app')

@section('title', 'Dispatch Report — DropFlow SFM')
@section('page-title', 'Dispatch Report')
@section('page-subtitle', 'Daily supplier dispatch batches — what left the warehouse.')

@section('content')
@include('reports.dispatch.partials.filters')

<div class="dispatch-report-summary grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Total Batches</p>
        <p class="dispatch-report-card-value">{{ number_format($totals['batches'] ?? 0) }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Dispatched Orders</p>
        <p class="dispatch-report-card-value">{{ number_format($totals['orders'] ?? 0) }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Dispatched Qty</p>
        <p class="dispatch-report-card-value">{{ number_format($totals['qty'] ?? 0) }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Supplier Cost</p>
        <p class="dispatch-report-card-value tabular-nums">{{ number_format($totals['supplier_cost'] ?? 0, 2) }}</p>
    </div>
</div>

<div class="dispatch-report-table-wrap bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
        <h2 class="font-medium text-slate-900">Dispatch Batches</h2>
        <p class="text-xs text-slate-500 mt-1">One batch per supplier dispatch run — not individual finance entries.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Batch No</th>
                    <th class="text-left font-medium text-slate-600">Dispatch Date</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Store</th>
                    <th class="text-right font-medium text-slate-600">Orders</th>
                    <th class="text-right font-medium text-slate-600">Qty</th>
                    <th class="text-right font-medium text-slate-600">Supplier Cost</th>
                    <th class="text-left font-medium text-slate-600">Status</th>
                    <th class="text-right font-medium text-slate-600">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($batches as $batch)
                    <tr class="hover:bg-slate-50">
                        <td class="font-mono text-xs font-medium text-slate-900">{{ $batch->batch_no }}</td>
                        <td class="text-slate-700">{{ $batch->dispatch_date->format('M j, Y') }}</td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $batch->supplier?->name }}</td>
                        @endif
                        <td class="text-slate-600 text-xs">{{ $batch->connection?->store_url ? parse_url($batch->connection->store_url, PHP_URL_HOST) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $batch->total_orders }}</td>
                        <td class="text-right tabular-nums">{{ $batch->total_qty }}</td>
                        <td class="text-right font-medium tabular-nums text-slate-900">{{ number_format($batch->total_supplier_cost, 2) }}</td>
                        <td class="text-slate-600">{{ $batch->status->label() }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('reports.dispatch.show', $batch) }}" class="text-sky-700 hover:text-sky-900 font-medium">View</a>
                            <span class="text-slate-300 mx-1">|</span>
                            <a href="{{ route('reports.dispatch.print', $batch) }}" class="text-sky-700 hover:text-sky-900 font-medium" target="_blank" rel="noopener">Print</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 9 : 8 }}" class="text-center text-slate-500 py-12">
                            No dispatch batches match filters. Create a batch from packed orders in Order Queue.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
