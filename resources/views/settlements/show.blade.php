@extends('layouts.app')

@section('title', 'Settlement Batch '.$batch->batch_no.' — DropFlow SFM')
@section('page-title', 'Settlement Batch '.$batch->batch_no)
@section('page-subtitle', $batch->supplier?->name.' · '.($batch->connection?->store_url ? parse_url($batch->connection->store_url, PHP_URL_HOST) : 'All stores'))

@section('page-actions')
    <a href="{{ route('settlements.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Back to history
    </a>
@endsection

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Opening Balance</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">{{ number_format($batch->opening_balance, 2) }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Closing Balance</p>
        @include('partials.balance-display', [
            'amount' => $batch->closing_balance,
            'amountClass' => 'text-xl font-semibold',
        ])
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Who Paid Whom</p>
        <p class="mt-1 text-sm font-medium text-slate-900">{{ $who_paid_whom }}</p>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-4">
        <p class="text-xs font-medium text-slate-500">Closed</p>
        <p class="mt-1 text-sm text-slate-900">{{ $batch->closed_at?->format('M j, Y g:i A') }}</p>
        <p class="text-xs text-slate-500 mt-1">by {{ $batch->closedBy?->name ?? '—' }}</p>
    </div>
</div>

@if ($batch->notes)
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-700">
        <p class="text-xs font-medium text-slate-500 mb-1">Notes</p>
        <p>{{ $batch->notes }}</p>
    </div>
@endif

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h2 class="font-medium text-slate-900">Transactions Included</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left font-medium text-slate-600">Date</th>
                    <th class="text-left font-medium text-slate-600">Type</th>
                    <th class="text-left font-medium text-slate-600">Order</th>
                    <th class="text-right font-medium text-slate-600">Amount</th>
                    <th class="text-left font-medium text-slate-600">Reference</th>
                    <th class="text-left font-medium text-slate-600">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($transactions as $entry)
                    @php
                        $type = $entry->type instanceof \App\Enums\LedgerEntryType
                            ? $entry->type
                            : \App\Enums\LedgerEntryType::tryFrom((string) $entry->type);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="text-slate-900">{{ $entry->entry_date->format('M j, Y') }}</td>
                        <td class="text-slate-600">{{ $type?->label() ?? (string) $entry->type }}</td>
                        <td class="text-slate-600 font-mono text-xs">{{ $entry->order?->source_order_id ?? '—' }}</td>
                        <td class="text-right font-medium tabular-nums {{ (float) $entry->amount < 0 ? 'text-orange-600' : 'text-slate-900' }}">{{ number_format($entry->amount, 2) }}</td>
                        <td class="text-slate-600 font-mono text-xs">{{ $entry->reference ?? '—' }}</td>
                        <td class="text-slate-500 text-xs">{{ $entry->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-slate-500 py-12">No transactions in this batch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
