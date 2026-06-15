@extends('layouts.app')

@section('title', 'Dispatch Report — DropFlow SFM')
@section('page-title', 'Dispatch Report')
@section('page-subtitle', 'Dispatched orders with cost totals')

@section('content')
@include('reports.partials.filters')

<div class="mb-4 rounded-lg bg-white border border-slate-200 px-5 py-3 text-sm">
    <span class="text-slate-500">Total dispatch cost:</span>
    <span class="ml-2 font-semibold text-slate-900">{{ number_format($totals['dispatch_cost'] ?? 0, 2) }}</span>
    <span class="mx-3 text-slate-300">|</span>
    <span class="text-slate-500">Reports:</span>
    <span class="ml-2 font-semibold text-slate-900">{{ $totals['count'] ?? count($rows ?? []) }}</span>
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Date</th>
                    <th class="text-left font-medium text-slate-600">Order</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Courier</th>
                    <th class="text-left font-medium text-slate-600">Consignment</th>
                    <th class="text-right font-medium text-slate-600">Total Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows ?? [] as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="text-slate-900">{{ $row->dispatch_date->format('M j, Y') }}</td>
                        <td class="font-medium text-slate-900">#{{ $row->order?->source_order_id }}</td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $row->supplier?->name }}</td>
                        @endif
                        <td class="text-slate-600">{{ $row->courier }}</td>
                        <td class="text-slate-600 font-mono text-xs">{{ $row->consignment_id }}</td>
                        <td class="text-right font-medium text-slate-900">{{ number_format($row->total_cost ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}" class="text-center text-slate-500 py-12">No dispatch records match filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
