@extends('layouts.app')

@section('title', 'Payables — DropFlow SFM')
@section('page-title', 'Payables')
@section('page-subtitle', 'Record supplier payments and see current payable balance.')

@section('content')
@if (auth()->user()->isAdmin())
    <form method="GET" action="{{ route('payables.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="supplier_id" class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
            <select name="supplier_id" id="supplier_id" required
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
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

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <p class="text-sm font-medium text-slate-500">Total Dispatched Cost</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['delivered_cost'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <p class="text-sm font-medium text-slate-500">Total Return Cost</p>
        <p class="mt-2 text-2xl font-semibold text-orange-600">{{ number_format($summary['returned_cost'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <p class="text-sm font-medium text-slate-500">Total Paid</p>
        <p class="mt-2 text-2xl font-semibold text-slate-700">{{ number_format($summary['total_paid'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5 ring-2 ring-emerald-100">
        <p class="text-sm font-medium text-slate-500">Current Balance</p>
        <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['net_payable'] ?? 0, 2) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 class="font-medium text-slate-900">Settlement History</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left font-medium text-slate-600">Date</th>
                            <th class="text-left font-medium text-slate-600">Type</th>
                            <th class="text-right font-medium text-slate-600">Amount</th>
                            <th class="text-left font-medium text-slate-600">Reference</th>
                            <th class="text-left font-medium text-slate-600">Notes</th>
                            <th class="text-left font-medium text-slate-600">Recorded By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($settlements ?? [] as $entry)
                            <tr>
                                <td class="text-slate-900">{{ $entry->entry_date->format('M j, Y') }}</td>
                                <td class="text-slate-600">{{ $entry->entry_type->label() }}</td>
                                <td class="text-right font-medium text-slate-900">{{ number_format($entry->amount, 2) }}</td>
                                <td class="text-slate-600">{{ $entry->reference ?? '—' }}</td>
                                <td class="text-slate-600 max-w-xs truncate">{{ $entry->notes ?? '—' }}</td>
                                <td class="text-slate-500 text-xs">{{ $entry->recordedBy?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-slate-500 py-8">No settlement entries recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if (auth()->user()->isAdmin())
        <div>
            <div class="bg-white rounded-lg border border-slate-200 p-6">
                <h2 class="font-medium text-slate-900 mb-4">Record Settlement</h2>
                <p class="text-xs text-slate-500 mb-4">Dispatch and return entries post automatically. Use this form for payments and adjustments.</p>

                <form method="POST" action="{{ route('payables.settlements.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplierId ?? ($suppliers->first()?->id ?? '') }}">

                    <div>
                        <label for="entry_type" class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                        <select name="entry_type" id="entry_type" required
                                class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($settlementTypes ?? [] as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                        <input type="number" step="0.01" name="amount" id="amount" required
                               class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <p class="mt-1 text-xs text-slate-500">Use positive amounts. Adjustments may be negative.</p>
                    </div>

                    <div>
                        <label for="entry_date" class="block text-sm font-medium text-slate-700 mb-1">Entry Date</label>
                        <input type="date" name="entry_date" id="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required
                               class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>

                    <div>
                        <label for="reference" class="block text-sm font-medium text-slate-700 mb-1">Reference</label>
                        <input type="text" name="reference" id="reference" value="{{ old('reference') }}"
                               class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                        <textarea name="notes" id="notes" rows="3"
                                  class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Record settlement
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="bg-white rounded-lg border border-slate-200 p-6 text-sm text-slate-600">
            <h2 class="font-medium text-slate-900 mb-2">Balance Formula</h2>
            <p class="text-xs leading-relaxed">
                Current Balance = Total Dispatched Cost − Total Return Cost − Total Paid.
                Sale prices are not included in supplier settlement.
            </p>
        </div>
    @endif
</div>
@endsection
