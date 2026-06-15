@extends('layouts.app')

@section('title', 'Dispatch Report — DropFlow SFM')
@section('page-title', 'Dispatch Report')
@section('page-subtitle', 'Dispatched orders with cost snapshots')

@section('content')
<form method="GET" action="{{ route('dispatch-report.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
    <div>
        <label for="from" class="block text-xs font-medium text-slate-600 mb-1">From</label>
        <input type="date" name="from" id="from" value="{{ request('from') }}"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
    </div>
    <div>
        <label for="to" class="block text-xs font-medium text-slate-600 mb-1">To</label>
        <input type="date" name="to" id="to" value="{{ request('to') }}"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
    </div>
    @if (auth()->user()->isAdmin())
        <div>
            <label for="supplier_id" class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
            <select name="supplier_id" id="supplier_id"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">All suppliers</option>
                @foreach ($suppliers ?? [] as $supplier)
                    <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Filter
    </button>
</form>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Dispatch Date</th>
                    <th class="text-left font-medium text-slate-600">Order</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Courier</th>
                    <th class="text-left font-medium text-slate-600">Consignment</th>
                    <th class="text-right font-medium text-slate-600">Items</th>
                    <th class="text-right font-medium text-slate-600">Total Cost</th>
                    <th class="text-left font-medium text-slate-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($reports as $report)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap text-slate-900">{{ $report->dispatch_date->format('M j, Y') }}</td>
                        <td class="font-medium text-slate-900">#{{ $report->order?->source_order_id }}</td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $report->supplier?->name }}</td>
                        @endif
                        <td class="text-slate-600">{{ $report->courier }}</td>
                        <td class="text-slate-600 font-mono text-xs">{{ $report->consignment_id }}</td>
                        <td class="text-right text-slate-900">{{ $report->items_count ?? $report->items?->count() ?? 0 }}</td>
                        <td class="text-right font-medium text-slate-900">{{ number_format($report->total_cost ?? 0, 2) }}</td>
                        <td>
                            <a href="{{ route('dispatch-report.show', $report) }}" class="text-sm text-slate-600 hover:text-slate-900 underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="text-center text-slate-500 py-12">No dispatch reports found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if (isset($reports) && method_exists($reports, 'links'))
    <div class="mt-4">{{ $reports->links() }}</div>
@endif
@endsection
