@extends('layouts.app')

@section('title', 'Supplier Payable Report — DropFlow SFM')
@section('page-title', auth()->user()->isAdmin() ? 'Supplier Payable Report' : 'Payable Summary Report')
@section('page-subtitle', 'Payable breakdown by supplier')

@section('content')
@include('reports.partials.filters')

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Delivered Cost</p>
        <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($summary['delivered_cost'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Returned Cost</p>
        <p class="mt-1 text-xl font-semibold text-orange-600">{{ number_format($summary['returned_cost'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Received</p>
        <p class="mt-1 text-xl font-semibold text-slate-700">{{ number_format($summary['received_amount'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Net Payable</p>
        <p class="mt-1 text-xl font-semibold text-emerald-700">{{ number_format($summary['net_payable'] ?? 0, 2) }}</p>
    </div>
</div>

@if (auth()->user()->isAdmin() && isset($rows))
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="font-medium text-slate-900">By Supplier</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                        <th class="text-right font-medium text-slate-600">Delivered</th>
                        <th class="text-right font-medium text-slate-600">Returned</th>
                        <th class="text-right font-medium text-slate-600">Received</th>
                        <th class="text-right font-medium text-slate-600">Net Payable</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="font-medium text-slate-900">{{ $row['supplier_name'] ?? $row->supplier_name ?? '—' }}</td>
                            <td class="text-right text-slate-900">{{ number_format($row['delivered_cost'] ?? $row->delivered_cost ?? 0, 2) }}</td>
                            <td class="text-right text-orange-600">{{ number_format($row['returned_cost'] ?? $row->returned_cost ?? 0, 2) }}</td>
                            <td class="text-right text-slate-700">{{ number_format($row['received_amount'] ?? $row->received_amount ?? 0, 2) }}</td>
                            <td class="text-right font-semibold text-emerald-700">{{ number_format($row['net_payable'] ?? $row->net_payable ?? 0, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-slate-500 py-12">No payable data for selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
