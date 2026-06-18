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
                    <label for="supplier_id" class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
                    <select name="supplier_id" id="supplier_id" required class="w-full rounded-md border-slate-300 text-sm">
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
                    <label for="connection_id" class="block text-xs font-medium text-slate-600 mb-1">Store</label>
                    <select name="connection_id" id="connection_id" class="w-full rounded-md border-slate-300 text-sm">
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('connection_id', $selectedConnectionId) == $store->id)>
                                {{ parse_url($store->store_url, PHP_URL_HOST) ?: $store->store_url }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="entry_type" class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                <select name="entry_type" id="entry_type" required class="w-full rounded-md border-slate-300 text-sm">
                    @foreach ($entryTypes as $key => $type)
                        <option value="{{ $key }}" @selected(old('entry_type') === $key)>{{ $type->operationalLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="collection_source" class="block text-xs font-medium text-slate-600 mb-1">Source</label>
                <select name="collection_source" id="collection_source" class="w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}" @selected(old('collection_source') === $source->value)>{{ $source->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="entry_date" class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                <input type="date" name="entry_date" id="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required class="w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label for="amount" class="block text-xs font-medium text-slate-600 mb-1">Amount</label>
                <input type="number" step="0.01" name="amount" id="amount" value="{{ old('amount') }}" required class="w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label for="reference" class="block text-xs font-medium text-slate-600 mb-1">Reference</label>
                <input type="text" name="reference" id="reference" value="{{ old('reference') }}" class="w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label for="notes" class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="2" class="w-full rounded-md border-slate-300 text-sm">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Save Entry
            </button>
        </form>
    </div>

    <div class="xl:col-span-2">
        <form method="GET" action="{{ route('reports.collections') }}" class="mb-4 flex flex-wrap items-end gap-3">
            @if (! ($singleSupplier ?? false))
                <div>
                    <label for="filter_supplier_id" class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
                    <select name="supplier_id" id="filter_supplier_id" class="rounded-md border-slate-300 text-sm">
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected($selectedSupplierId == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="supplier_id" value="{{ $defaultSupplierId }}">
            @endif
            <div>
                <label for="filter_entry_type" class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                <select name="entry_type" id="filter_entry_type" class="rounded-md border-slate-300 text-sm">
                    <option value="">All</option>
                    @foreach ($entryTypes as $key => $type)
                        <option value="{{ $key }}" @selected($selectedEntryType === $key)>{{ $type->operationalLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="from" class="block text-xs font-medium text-slate-600 mb-1">From</label>
                <input type="date" name="from" id="from" value="{{ $from }}" class="rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-slate-600 mb-1">To</label>
                <input type="date" name="to" id="to" value="{{ $to }}" class="rounded-md border-slate-300 text-sm">
            </div>
            <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
        </form>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
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
