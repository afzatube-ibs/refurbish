@extends('layouts.app')

@section('title', 'Product Stock Report — DropFlow SFM')
@section('page-title', 'Product Stock Report')
@section('page-subtitle', 'Current supplier product inventory')

@section('content')
@include('reports.partials.filters')

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    @if (auth()->user()->isAdmin())
                        <th class="text-left font-medium text-slate-600">Supplier</th>
                    @endif
                    <th class="text-left font-medium text-slate-600">Product</th>
                    <th class="text-left font-medium text-slate-600">Model</th>
                    <th class="text-right font-medium text-slate-600">OC Stock</th>
                    <th class="text-right font-medium text-slate-600">Supplier Stock</th>
                    <th class="text-right font-medium text-slate-600">Supplier Cost</th>
                    <th class="text-right font-medium text-slate-600">Low Warning</th>
                    <th class="text-left font-medium text-slate-600">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows ?? [] as $row)
                    <tr class="hover:bg-slate-50 {{ ($row->low_warning && $row->stock <= $row->low_warning) ? 'bg-amber-50' : '' }}">
                        @if (auth()->user()->isAdmin())
                            <td class="text-slate-600">{{ $row->supplier?->name ?? '—' }}</td>
                        @endif
                        <td class="font-medium text-slate-900">{{ $row->name }}</td>
                        <td class="text-slate-600">{{ $row->model }}</td>
                        <td class="text-right text-slate-900">{{ $row->stock }}</td>
                        <td class="text-right text-slate-600">{{ $row->supplier_stock ?? '—' }}</td>
                        <td class="text-right text-slate-900">{{ number_format($row->supplier_cost, 2) }}</td>
                        <td class="text-right text-slate-600">{{ $row->low_warning ?? '—' }}</td>
                        <td>
                            @if ($row->low_warning && $row->stock <= $row->low_warning)
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Low</span>
                            @else
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">OK</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="text-center text-slate-500 py-12">No products match filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
