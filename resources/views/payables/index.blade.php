@extends('layouts.app')

@section('title', 'Payables — DropFlow SFM')
@section('page-title', 'Payables')
@section('page-subtitle', auth()->user()->isAdmin() ? 'Supplier payable summary and payments' : 'Your payable summary (read-only)')

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
        <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Apply
        </button>
    </form>
@endif

{{-- Summary cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <p class="text-sm font-medium text-slate-500">Delivered Product Cost</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['delivered_cost'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <p class="text-sm font-medium text-slate-500">Returned Product Cost</p>
        <p class="mt-2 text-2xl font-semibold text-orange-600">{{ number_format($summary['returned_cost'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <p class="text-sm font-medium text-slate-500">Received from Supplier</p>
        <p class="mt-2 text-2xl font-semibold text-slate-700">{{ number_format($summary['received_amount'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5 ring-2 ring-emerald-100">
        <p class="text-sm font-medium text-slate-500">Net Payable</p>
        <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['net_payable'] ?? 0, 2) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 class="font-medium text-slate-900">Payment History</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left font-medium text-slate-600">Date</th>
                            <th class="text-right font-medium text-slate-600">Amount</th>
                            <th class="text-left font-medium text-slate-600">Reference</th>
                            <th class="text-left font-medium text-slate-600">Notes</th>
                            <th class="text-left font-medium text-slate-600">Recorded By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments ?? [] as $payment)
                            <tr>
                                <td class="text-slate-900">{{ $payment->payment_date->format('M j, Y') }}</td>
                                <td class="text-right font-medium text-slate-900">{{ number_format($payment->amount, 2) }}</td>
                                <td class="text-slate-600">{{ $payment->reference ?? '—' }}</td>
                                <td class="text-slate-600 max-w-xs truncate">{{ $payment->notes ?? '—' }}</td>
                                <td class="text-slate-500 text-xs">{{ $payment->recorder?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-slate-500 py-8">No payments recorded.</td>
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
                <h2 class="font-medium text-slate-900 mb-4">Record Payment</h2>
                <p class="text-xs text-slate-500 mb-4">Positive amount = payment received from supplier toward payable balance.</p>

                <form method="POST" action="{{ route('payables.payments.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplierId ?? ($suppliers->first()?->id ?? '') }}">

                    <div>
                        <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="amount" required
                               class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>

                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-slate-700 mb-1">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required
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
                        Record payment
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="bg-white rounded-lg border border-slate-200 p-6 text-sm text-slate-600">
            <h2 class="font-medium text-slate-900 mb-2">Payable Formula</h2>
            <p class="text-xs leading-relaxed">
                Net Payable = Delivered Product Cost − Returned Product Cost − Received from Supplier.
                Sale prices are not included in this calculation.
            </p>
        </div>
    @endif
</div>
@endsection
