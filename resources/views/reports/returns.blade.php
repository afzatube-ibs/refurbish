@extends('layouts.app')

@section('title', 'Returns Report — DropFlow SFM')
@section('page-title', 'Returns Report')
@section('page-subtitle', 'Return pending and confirmed returns')

@section('content')
@include('reports.partials.filters')

<div class="mb-4 rounded-lg bg-white border border-slate-200 px-5 py-3 text-sm">
    <span class="text-slate-500">Confirmed return cost:</span>
    <span class="ml-2 font-semibold text-orange-600">{{ number_format($totals['confirmed_cost'] ?? 0, 2) }}</span>
    <span class="mx-3 text-slate-300">|</span>
    <span class="text-slate-500">Pending:</span>
    <span class="ml-2 font-semibold text-slate-900">{{ $totals['pending_count'] ?? 0 }}</span>
</div>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Order</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Status</th>
                    <th class="text-right font-medium text-slate-600">Return Cost</th>
                    <th class="text-left font-medium text-slate-600">Received Date</th>
                    <th class="text-left font-medium text-slate-600">Confirmed By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows ?? [] as $row)
                    @php
                        $statusValue = $row->return_status instanceof \App\Enums\ReturnStatus
                            ? $row->return_status->value
                            : $row->return_status;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="font-medium text-slate-900">#{{ $row->order?->source_order_id }}</td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $row->supplier?->name }}</td>
                        @endif
                        <td>
                            @if ($statusValue === 'confirmed')
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Confirmed</span>
                            @else
                                <span class="inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800">Pending</span>
                            @endif
                        </td>
                        <td class="text-right font-medium text-slate-900">{{ number_format($row->return_cost ?? 0, 2) }}</td>
                        <td class="text-slate-600">{{ $row->received_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="text-slate-500 text-xs">{{ $row->confirmedBy?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}" class="text-center text-slate-500 py-12">No returns match filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
