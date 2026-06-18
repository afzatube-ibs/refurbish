@extends('layouts.app')

@section('title', 'Dispatch Report — DropFlow SFM')
@section('page-title', 'Dispatch Report')
@section('page-subtitle', 'What supplier shipped and dispatched — order lines with supplier cost.')

@section('content')
@include('reports.dispatch.partials.filters')

<div class="dispatch-report-summary grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Dispatched Orders</p>
        <p class="dispatch-report-card-value">{{ number_format($totals['orders'] ?? 0) }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Dispatched Qty</p>
        <p class="dispatch-report-card-value">{{ number_format($totals['qty'] ?? 0) }}</p>
    </div>
    <div class="dispatch-report-card">
        <p class="dispatch-report-card-label">Dispatch Cost</p>
        <p class="dispatch-report-card-value tabular-nums">{{ number_format($totals['dispatch_cost'] ?? 0, 2) }}</p>
    </div>
</div>

<div class="dispatch-report-table-wrap bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
        <h2 class="font-medium text-slate-900">Dispatched Orders</h2>
        <p class="text-xs text-slate-500 mt-1">One row per product line — operational dispatch only.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Date</th>
                    <th class="text-left font-medium text-slate-600">Order No</th>
                    <th class="text-left font-medium text-slate-600">Customer</th>
                    <th class="text-left font-medium text-slate-600">Phone</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Store</th>
                    <th class="text-left font-medium text-slate-600">Product</th>
                    <th class="text-right font-medium text-slate-600">Qty</th>
                    <th class="text-right font-medium text-slate-600">Unit Cost</th>
                    <th class="text-right font-medium text-slate-600">Total Cost</th>
                    <th class="text-left font-medium text-slate-600">Courier</th>
                    <th class="text-left font-medium text-slate-600">Consignment</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($lines as $line)
                    <tr class="hover:bg-slate-50">
                        <td class="text-slate-600 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($line['date'])->format('M j, Y') }}</td>
                        <td class="font-medium text-slate-900">{{ $line['order_no'] }}</td>
                        <td class="text-slate-700">{{ $line['customer'] }}</td>
                        <td class="text-slate-600 text-xs">{{ $line['phone'] }}</td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $line['supplier'] }}</td>
                        @endif
                        <td class="text-slate-600 text-xs">{{ $line['store'] }}</td>
                        <td class="text-slate-700">{{ $line['product'] }}</td>
                        <td class="text-right tabular-nums">{{ $line['qty'] }}</td>
                        <td class="text-right tabular-nums text-slate-700">{{ number_format($line['supplier_unit_cost'], 2) }}</td>
                        <td class="text-right tabular-nums font-medium text-slate-900">{{ number_format($line['supplier_total_cost'], 2) }}</td>
                        <td class="text-slate-600">{{ $line['courier'] }}</td>
                        <td class="text-slate-600 font-mono text-xs">{{ $line['consignment_id'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 12 : 11 }}" class="text-center text-slate-500 py-12">
                            No dispatched orders match filters. Dispatch from Order Queue to appear here.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
