@extends('layouts.app')

@section('title', 'Payables Summary Report — DropFlow SFM')
@section('page-title', 'Payables Summary Report')
@section('page-subtitle', 'Read-only supplier payable breakdown by store')

@section('content')
@include('reports.partials.filters')

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h2 class="font-medium text-slate-900">Supplier Payable Summary</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Supplier</th>
                    <th class="text-left font-medium text-slate-600">Store</th>
                    <th class="text-right font-medium text-slate-600">Dispatch Cost</th>
                    <th class="text-right font-medium text-slate-600">Return Cost</th>
                    <th class="text-right font-medium text-slate-600">Paid</th>
                    <th class="text-right font-medium text-slate-600">Current Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="font-medium text-slate-900">{{ $row['supplier_name'] }}</td>
                        <td class="text-slate-600">{{ $row['store_name'] }}</td>
                        <td class="text-right text-slate-900">{{ number_format($row['delivered_cost'], 2) }}</td>
                        <td class="text-right text-orange-600">{{ number_format($row['returned_cost'], 2) }}</td>
                        <td class="text-right text-slate-700">{{ number_format($row['paid_amount'], 2) }}</td>
                        <td class="text-right font-semibold text-emerald-700">{{ number_format($row['net_payable'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-slate-500 py-12">No payable summary found for selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
