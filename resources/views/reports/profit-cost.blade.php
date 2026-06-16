@extends('layouts.app')

@section('title', 'Profit & Cost — DropFlow SFM')
@section('page-title', 'Profit & Cost Report')
@section('page-subtitle', 'Placeholder: sale amount vs snapshotted supplier cost on dispatched orders')

@section('content')
@include('reports.partials.filters')

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Total Sale</p>
        <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($totals['sale_amount'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Supplier Cost</p>
        <p class="mt-1 text-xl font-semibold text-orange-600">{{ number_format($totals['supplier_cost'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Est. Margin</p>
        <p class="mt-1 text-xl font-semibold text-emerald-700">{{ number_format($totals['margin'] ?? 0, 2) }}</p>
    </div>
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Order</th>
                    <th class="text-left font-medium text-slate-600">Supplier</th>
                    <th class="text-left font-medium text-slate-600">Status</th>
                    <th class="text-right font-medium text-slate-600">Sale</th>
                    <th class="text-right font-medium text-slate-600">Cost</th>
                    <th class="text-right font-medium text-slate-600">Margin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows ?? [] as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="font-medium text-slate-900">#{{ $row['order']->source_order_id }}</td>
                        <td class="text-slate-600">{{ $row['order']->supplier?->name }}</td>
                        <td><x-status-badge :status="$row['order']->sfm_status" /></td>
                        <td class="text-right text-slate-900">{{ number_format($row['sale_amount'], 2) }}</td>
                        <td class="text-right text-orange-600">{{ number_format($row['supplier_cost'], 2) }}</td>
                        <td class="text-right font-medium {{ $row['margin'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                            {{ number_format($row['margin'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-slate-500 py-12">No dispatched or completed orders match filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
