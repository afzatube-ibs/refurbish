@extends('layouts.app')

@section('title', 'Collections Report — DropFlow SFM')
@section('page-title', 'Collections Report')
@section('page-subtitle', 'Manual money movement — received by supplier, payment to dropshipper, adjustments.')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="xl:col-span-1 bg-white rounded-lg border border-slate-200 p-6">
        <h2 class="font-medium text-slate-900 mb-4">Record Entry</h2>
        @if (($singleSupplier ?? false) && ($singleStore ?? false))
            <p class="text-xs text-slate-500 mb-4">
                Supplier: {{ $defaultSupplierName }} · Store: {{ $defaultStoreLabel }}
            </p>
        @endif
        <form method="POST" action="{{ route('reports.collections.store') }}" class="space-y-4">
            @csrf
            @if ($singleSupplier ?? false)
                <input type="hidden" name="supplier_id" value="{{ $defaultSupplierId }}">
            @else
                <div>
                    <label for="supplier_id" class="df-filter-label">Supplier</label>
                    <select name="supplier_id" id="supplier_id" required class="df-select w-full mt-1">
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $selectedSupplierId) == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($singleStore ?? false)
                <input type="hidden" name="connection_id" value="{{ $defaultConnectionId }}">
            @else
                <div>
                    <label for="connection_id" class="df-filter-label">Store</label>
                    <select name="connection_id" id="connection_id" class="df-select w-full mt-1">
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('connection_id', $selectedConnectionId) == $store->id)>
                                {{ parse_url($store->store_url, PHP_URL_HOST) ?: $store->store_url }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="entry_type" class="df-filter-label">Type</label>
                <select name="entry_type" id="entry_type" required class="df-select w-full mt-1">
                    @foreach ($entryTypes as $key => $type)
                        <option value="{{ $key }}" @selected(old('entry_type') === $key)>{{ $type->operationalLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="collection_source" class="df-filter-label">Source</label>
                <select name="collection_source" id="collection_source" class="df-select w-full mt-1">
                    <option value="">—</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}" @selected(old('collection_source') === $source->value)>{{ $source->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="entry_date" class="df-filter-label">Date</label>
                <input type="date" name="entry_date" id="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required class="df-date w-full mt-1">
            </div>
            <div>
                <label for="amount" class="df-filter-label">Amount</label>
                <input type="number" step="0.01" name="amount" id="amount" value="{{ old('amount') }}" required class="df-input w-full mt-1">
            </div>
            <div>
                <label for="reference" class="df-filter-label">Reference</label>
                <input type="text" name="reference" id="reference" value="{{ old('reference') }}" class="df-input w-full mt-1">
            </div>
            <div>
                <label for="notes" class="df-filter-label">Notes</label>
                <textarea name="notes" id="notes" rows="2" class="df-textarea w-full mt-1">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="df-btn df-btn--primary w-full">
                Save Entry
            </button>
        </form>
    </div>

    <div class="xl:col-span-2">
        <form method="GET" action="{{ route('reports.collections') }}" class="df-filter-row">
            @if (! ($singleSupplier ?? false))
                <div class="df-filter-group">
                    <label for="filter_supplier_id" class="df-filter-label">Supplier</label>
                    <select name="supplier_id" id="filter_supplier_id" class="df-select">
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected($selectedSupplierId == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="supplier_id" value="{{ $defaultSupplierId }}">
            @endif
            <div class="df-filter-group">
                <label for="filter_entry_type" class="df-filter-label">Type</label>
                <select name="entry_type" id="filter_entry_type" class="df-select">
                    <option value="">All</option>
                    @foreach ($entryTypes as $key => $type)
                        <option value="{{ $key }}" @selected($selectedEntryType === $key)>{{ $type->operationalLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="df-filter-group">
                <label for="from" class="df-filter-label">From</label>
                <input type="date" name="from" id="from" value="{{ $from }}" class="df-date">
            </div>
            <div class="df-filter-group">
                <label for="to" class="df-filter-label">To</label>
                <input type="date" name="to" id="to" value="{{ $to }}" class="df-date">
            </div>
            <div class="df-filter-actions">
                <button type="submit" class="df-btn df-btn--secondary">Filter</button>
            </div>
        </form>

        @php
            use App\Enums\SettlementEntryType;
            $receivedBySupplier = (float) $rows->where('entry_type', SettlementEntryType::PaidToStoreOwner)->sum('amount');
            $paymentToDropshipper = (float) $rows->where('entry_type', SettlementEntryType::ReceivedFromSupplier)->sum('amount');
            $adjustmentTotal = (float) $rows->where('entry_type', SettlementEntryType::Adjustment)->sum('amount');
            $netCollections = $receivedBySupplier - $paymentToDropshipper + $adjustmentTotal;
        @endphp
        @include('partials.df-summary-bar', ['items' => [
            ['label' => 'Received by Supplier', 'value' => number_format($receivedBySupplier, 2)],
            ['label' => 'Payment to Dropshipper', 'value' => number_format($paymentToDropshipper, 2)],
            ['label' => 'Adjustment', 'value' => number_format($adjustmentTotal, 2)],
            ['label' => 'Net Collections', 'value' => number_format($netCollections, 2)],
        ]])

        <div class="df-report-table-wrap">
            <div class="df-report-table-scroll">
                <table class="df-report-table min-w-full divide-y divide-slate-200 text-sm table-compact">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left font-medium text-slate-600">Date</th>
                            <th class="text-left font-medium text-slate-600">Supplier</th>
                            <th class="text-left font-medium text-slate-600">Store</th>
                            <th class="text-left font-medium text-slate-600">Type</th>
                            <th class="text-left font-medium text-slate-600">Source</th>
                            <th class="text-right font-medium text-slate-600">Amount</th>
                            <th class="text-left font-medium text-slate-600">Reference</th>
                            <th class="text-left font-medium text-slate-600">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="text-slate-600 whitespace-nowrap">{{ $row->entry_date->format('M j, Y') }}</td>
                                <td class="text-slate-900">{{ $row->supplier?->name }}</td>
                                <td class="text-slate-600 text-xs">{{ $row->connection?->store_url ? parse_url($row->connection->store_url, PHP_URL_HOST) : '—' }}</td>
                                <td class="text-slate-700">{{ $row->entry_type->operationalLabel() }}</td>
                                <td class="text-slate-600">{{ $row->collection_source?->label() ?? '—' }}</td>
                                <td class="text-right font-medium tabular-nums">{{ number_format($row->amount, 2) }}</td>
                                <td class="text-slate-600 text-xs">{{ $row->reference ?? '—' }}</td>
                                <td class="text-slate-500 text-xs max-w-xs truncate">{{ $row->notes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-slate-500 py-12">No collection entries yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
