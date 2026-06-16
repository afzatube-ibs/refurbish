@extends('layouts.app')

@section('title', 'Supplier Ledger — DropFlow SFM')
@section('page-title', 'Supplier Ledger')
@section('page-subtitle', 'Manual ledger entries by supplier')

@section('content')
@include('reports.partials.filters')

<div class="mb-4 rounded-lg bg-white border border-slate-200 px-5 py-3 text-sm">
    <span class="text-slate-500">Entries:</span>
    <span class="ml-2 font-semibold text-slate-900">{{ $totals['count'] ?? 0 }}</span>
    <span class="mx-3 text-slate-300">|</span>
    <span class="text-slate-500">Total amount:</span>
    <span class="ml-2 font-semibold text-slate-900">{{ number_format($totals['amount'] ?? 0, 2) }}</span>
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Date</th>
                    <th class="text-left font-medium text-slate-600">Supplier</th>
                    <th class="text-left font-medium text-slate-600">Type</th>
                    <th class="text-right font-medium text-slate-600">Amount</th>
                    <th class="text-left font-medium text-slate-600">Reference</th>
                    <th class="text-left font-medium text-slate-600">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows ?? [] as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="text-slate-900">{{ $row->entry_date->format('M j, Y') }}</td>
                        <td class="text-slate-600">{{ $row->supplier?->name }}</td>
                        <td class="text-slate-600 capitalize">{{ $row->type }}</td>
                        <td class="text-right font-medium text-slate-900">{{ number_format($row->amount, 2) }}</td>
                        <td class="text-slate-600 font-mono text-xs">{{ $row->reference ?? '—' }}</td>
                        <td class="text-slate-500 text-xs">{{ $row->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-slate-500 py-12">No ledger entries yet. Record payments and adjustments here in a future phase.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
