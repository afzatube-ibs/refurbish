@extends('layouts.app')

@section('title', 'Payables Report — DropFlow SFM')
@section('page-subtitle', 'Current payable by supplier and store')

@section('page-title', 'Payables Report')

@section('content')
@include('reports.partials.filters')

<div class="mb-4 rounded-lg bg-slate-50 border border-slate-200 px-5 py-3 text-sm text-slate-700">
    <strong>Current Payable</strong> =
    Dispatch Cost − Return Cost − Received by Supplier − Payment to Dropshipper ± Adjustment
    <span class="text-slate-500 ml-2">(positive = need to pay supplier)</span>
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h2 class="font-medium text-slate-900">Supplier Payable Summary</h2>
    </div>
    <div class="payables-report-wrap overflow-x-auto">
        <table class="payables-report-table min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Supplier</th>
                    <th class="text-left font-medium text-slate-600">Store</th>
                    <th class="text-right font-medium text-slate-600">Dispatch Cost</th>
                    <th class="text-right font-medium text-slate-600">Return Cost</th>
                    <th class="text-right font-medium text-slate-600">Received by Supplier</th>
                    <th class="text-right font-medium text-slate-600">Payment to Dropshipper</th>
                    <th class="text-right font-medium text-slate-600">Adjustment</th>
                    <th class="text-right font-medium text-slate-600 payables-balance-col">Current Payable</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    @php $payable = (float) ($row['current_payable'] ?? 0); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="font-medium text-slate-900">{{ $row['supplier_name'] }}</td>
                        <td class="text-slate-600">{{ $row['store_name'] }}</td>
                        <td class="text-right text-slate-900 tabular-nums">{{ number_format($row['dispatch_cost'], 2) }}</td>
                        <td class="text-right text-orange-600 tabular-nums">{{ number_format($row['return_cost'], 2) }}</td>
                        <td class="text-right text-slate-700 tabular-nums">{{ number_format($row['received_by_supplier'], 2) }}</td>
                        <td class="text-right text-slate-700 tabular-nums">{{ number_format($row['payment_to_dropshipper'], 2) }}</td>
                        <td class="text-right text-slate-700 tabular-nums">{{ number_format($row['adjustment'], 2) }}</td>
                        <td class="text-right payables-balance-col">
                            <p class="font-semibold tabular-nums {{ $row['payable_tone_class'] ?? '' }}">{{ number_format($payable, 2) }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $row['payable_meaning'] ?? '' }}</p>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-slate-500 py-12">No payable summary found for selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
