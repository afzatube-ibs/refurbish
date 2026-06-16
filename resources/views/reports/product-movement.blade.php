@extends('layouts.app')

@section('title', 'Product Movement — DropFlow SFM')
@section('page-title', 'Product Movement Report')
@section('page-subtitle', 'Stock adjustments from product control and order workflow')

@section('content')
@include('reports.partials.filters')

<div class="mb-4 rounded-lg bg-white border border-slate-200 px-5 py-3 text-sm">
    <span class="text-slate-500">Adjustments:</span>
    <span class="ml-2 font-semibold text-slate-900">{{ $totals['adjustments'] ?? 0 }}</span>
    <span class="mx-3 text-slate-300">|</span>
    <span class="text-slate-500">Net stock change:</span>
    <span class="ml-2 font-semibold {{ ($totals['net_change'] ?? 0) < 0 ? 'text-orange-600' : 'text-emerald-700' }}">
        {{ ($totals['net_change'] ?? 0) > 0 ? '+' : '' }}{{ $totals['net_change'] ?? 0 }}
    </span>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="font-medium text-slate-900">Stock Adjustment History</h2>
            <p class="text-xs text-slate-500 mt-1">Recent movements (max 500 rows)</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left font-medium text-slate-600">When</th>
                        <th class="text-left font-medium text-slate-600">Product</th>
                        <th class="text-right font-medium text-slate-600">Change</th>
                        <th class="text-left font-medium text-slate-600">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows ?? [] as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="text-slate-500 text-xs">{{ $row->created_at?->format('M j, Y H:i') }}</td>
                            <td class="text-slate-900">
                                <span class="font-mono text-xs">{{ $row->product_id }}</span>
                                @if ($row->variant_id)
                                    <span class="text-slate-500">/ {{ $row->variant_id }}</span>
                                @endif
                            </td>
                            <td class="text-right font-medium {{ $row->difference < 0 ? 'text-orange-600' : 'text-emerald-700' }}">
                                {{ $row->difference > 0 ? '+' : '' }}{{ $row->difference }}
                                <span class="text-slate-400 font-normal text-xs">({{ $row->old_stock }}→{{ $row->new_stock }})</span>
                            </td>
                            <td class="text-slate-600 text-xs">{{ $row->reason ?? $row->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-slate-500 py-12">No stock adjustments in selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="font-medium text-slate-900">Current Product Control</h2>
            <p class="text-xs text-slate-500 mt-1">Snapshot from product control states</p>
        </div>
        <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <th class="text-left font-medium text-slate-600">Product</th>
                        <th class="text-left font-medium text-slate-600">Category</th>
                        <th class="text-right font-medium text-slate-600">Rate</th>
                        <th class="text-right font-medium text-slate-600">Variants</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($controlStates ?? [] as $state)
                        <tr class="hover:bg-slate-50">
                            <td class="font-mono text-xs text-slate-900">{{ $state->source_product_id }}</td>
                            <td class="text-slate-600">{{ $state->product_category ?? '—' }}</td>
                            <td class="text-right text-slate-900">{{ number_format($state->rate ?? 0, 2) }}</td>
                            <td class="text-right text-slate-600">{{ $state->variants->sum('ibs_stock') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-slate-500 py-12">No product control data loaded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
