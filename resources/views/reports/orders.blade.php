@extends('layouts.app')

@section('title', 'Orders Report — DropFlow SFM')
@section('page-title', 'Orders Report')
@section('page-subtitle', 'Filtered order listing')

@section('content')
@include('reports.partials.filters')

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Order ID</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Customer</th>
                    <th class="text-right font-medium text-slate-600">Sale Amount</th>
                    <th class="text-left font-medium text-slate-600">OC Status</th>
                    <th class="text-left font-medium text-slate-600">SFM Status</th>
                    <th class="text-left font-medium text-slate-600">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows ?? [] as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="font-medium text-slate-900">#{{ $row->source_order_id }}</td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $row->supplier?->name }}</td>
                        @endif
                        <td class="text-slate-900">{{ $row->customer_name }}</td>
                        <td class="text-right text-slate-900">{{ number_format($row->sale_amount, 2) }}</td>
                        <td class="text-slate-600">{{ $row->current_oc_status }}</td>
                        <td><x-status-badge :status="$row->sfm_status" /></td>
                        <td class="text-slate-500 text-xs">{{ $row->oc_created_at?->format('M j, Y') ?? $row->created_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 7 : 6 }}" class="text-center text-slate-500 py-12">No orders match filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
