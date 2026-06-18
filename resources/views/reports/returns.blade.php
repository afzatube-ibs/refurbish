@extends('layouts.app')

@section('title', 'Returns Report — DropFlow SFM')
@section('page-title', 'Returns Report')
@section('page-subtitle', 'What came back — confirmed return cost by order.')

@section('content')
@include('reports.partials.filters')

@include('partials.df-summary-bar', ['items' => [
    ['label' => 'Returned orders', 'value' => number_format($totals['orders'] ?? 0)],
    ['label' => 'Returned qty', 'value' => number_format($totals['qty'] ?? 0)],
    ['label' => 'Return cost', 'value' => number_format($totals['return_cost'] ?? 0, 2), 'tone' => 'accent'],
]])

<div class="df-report-table-wrap">
    <div class="df-report-table-scroll">
        <table class="df-report-table min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Date</th>
                    <th class="text-left font-medium text-slate-600">Order No</th>
                    <th class="text-left font-medium text-slate-600">Customer</th>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Products</th>
                    <th class="text-right font-medium text-slate-600">Qty</th>
                    <th class="text-right font-medium text-slate-600">Return Cost</th>
                    <th class="text-left font-medium text-slate-600">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows ?? [] as $row)
                    @php
                        $statusValue = $row['status'] instanceof \App\Enums\ReturnStatus
                            ? $row['status']->value
                            : $row['status'];
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="text-slate-600 whitespace-nowrap">{{ $row['date']?->format('M j, Y') ?? '—' }}</td>
                        <td class="font-medium text-slate-900">#{{ $row['order_no'] }}</td>
                        <td class="text-slate-700">{{ $row['customer'] }}</td>
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $row['supplier'] }}</td>
                        @endif
                        <td class="text-slate-600 text-xs max-w-xs">{{ $row['items_summary'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['qty'] }}</td>
                        <td class="text-right font-medium text-orange-600 tabular-nums">{{ number_format($row['return_cost'] ?? 0, 2) }}</td>
                        <td>
                            @if ($statusValue === 'confirmed')
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Confirmed</span>
                            @else
                                <span class="inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800">{{ ucfirst($statusValue) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="text-center text-slate-500 py-12">No returns match filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
