@extends('layouts.app')

@section('title', 'Dispatch Report — DropFlow SFM')
@section('page-title', 'Dispatch Report')
@section('page-subtitle', 'What supplier shipped and dispatched — order lines with supplier cost.')

@section('content')
@include('reports.dispatch.partials.filters')

@include('partials.df-summary-bar', ['items' => [
    ['label' => 'Dispatched orders', 'value' => number_format($totals['orders'] ?? 0)],
    ['label' => 'Dispatched qty', 'value' => number_format($totals['qty'] ?? 0)],
    ['label' => 'Dispatch cost', 'value' => number_format($totals['dispatch_cost'] ?? 0, 2)],
]])

<div class="df-report-table-wrap">
    <div class="df-report-table-header">
        <h2>Dispatched Orders</h2>
        <p>One row per product line — operational dispatch only.</p>
    </div>
    <div class="df-report-table-scroll">
        <table class="df-report-table min-w-full divide-y divide-slate-200 text-sm table-compact">
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
