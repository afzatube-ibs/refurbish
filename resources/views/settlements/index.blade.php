@extends('layouts.app')

@section('title', 'Settlement History — DropFlow SFM')
@section('page-title', 'Settlement History')
@section('page-subtitle', 'Closed settlement batches by supplier and store.')

@section('content')
@if (auth()->user()->isAdmin())
    <form method="GET" action="{{ route('settlements.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="supplier_id" class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
            <select name="supplier_id" id="supplier_id"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">All suppliers</option>
                @foreach ($suppliers ?? [] as $supplier)
                    <option value="{{ $supplier->id }}" @selected(($selectedSupplierId ?? null) == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Apply
        </button>
    </form>
@endif

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h2 class="font-medium text-slate-900">Settlement Batches</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Batch No</th>
                    <th class="text-left font-medium text-slate-600">Supplier</th>
                    <th class="text-left font-medium text-slate-600">Store</th>
                    <th class="text-right font-medium text-slate-600">Amount</th>
                    <th class="text-left font-medium text-slate-600">Direction</th>
                    <th class="text-left font-medium text-slate-600">Date</th>
                    <th class="text-left font-medium text-slate-600">Status</th>
                    <th class="text-left font-medium text-slate-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($batches as $batch)
                    <tr class="hover:bg-slate-50">
                        <td class="font-mono text-xs text-slate-900">{{ $batch->batch_no }}</td>
                        <td class="text-slate-900">{{ $batch->supplier?->name }}</td>
                        <td class="text-slate-600 text-xs">{{ $batch->connection?->store_url ? parse_url($batch->connection->store_url, PHP_URL_HOST) : '—' }}</td>
                        <td class="text-right font-medium tabular-nums text-slate-900">{{ number_format($batch->displayAmount(), 2) }}</td>
                        <td class="text-slate-600">{{ $batch->direction->shortLabel() }}</td>
                        <td class="text-slate-600">{{ $batch->closed_at?->format('M j, Y') }}</td>
                        <td class="text-slate-600">{{ $batch->status->label() }}</td>
                        <td class="text-right">
                            <a href="{{ route('settlements.show', $batch) }}" class="text-slate-700 hover:text-slate-900 font-medium">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-slate-500 py-12">No closed settlement batches yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($batches->hasPages())
        <div class="px-6 py-4 border-t border-slate-200">
            {{ $batches->links() }}
        </div>
    @endif
</div>
@endsection
