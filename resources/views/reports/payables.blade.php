@extends('layouts.app')

@section('title', 'Payables Report — DropFlow SFM')
@section('page-subtitle', 'Current payable by supplier and store')

@section('page-title', 'Payables Report')

@section('content')
@include('reports.partials.filters')

@php
    $summaryDispatch = (float) collect($rows)->sum('dispatch_cost');
    $summaryReturn = (float) collect($rows)->sum('return_cost');
    $summaryReceived = (float) collect($rows)->sum('received_by_supplier');
    $summaryPayment = (float) collect($rows)->sum('payment_to_dropshipper');
    $summaryPayable = (float) collect($rows)->sum('current_payable');
@endphp
@include('partials.df-summary-bar', ['items' => [
    ['label' => 'Dispatch Cost', 'value' => number_format($summaryDispatch, 2)],
    ['label' => 'Return Cost', 'value' => number_format($summaryReturn, 2), 'tone' => 'accent'],
    ['label' => 'Received by Supplier', 'value' => number_format($summaryReceived, 2)],
    ['label' => 'Payment to Dropshipper', 'value' => number_format($summaryPayment, 2)],
    ['label' => 'Current Payable', 'value' => number_format($summaryPayable, 2)],
]])

<p class="df-summary-note">
    <strong>Current Payable</strong> = Dispatch Cost − Return Cost − Received by Supplier − Payment to Dropshipper ± Adjustment
    <span class="text-slate-400">(positive = need to pay supplier)</span>
</p>

<div class="df-report-table-wrap">
    <div class="df-report-table-header">
        <h2>Supplier Payable Summary</h2>
    </div>
    <div class="payables-report-wrap df-report-table-scroll">
        <table class="payables-report-table df-report-table min-w-full divide-y divide-slate-200 text-sm table-compact">
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
