@extends('layouts.app')

@section('title', 'Product Map — DropFlow SFM')
@section('page-title', 'Product Map')
@section('page-subtitle', 'Step 2A — Product preview layer (read-only, no import)')

@section('content')
@php
    $hasPreview = ! empty($products);
@endphp

<div class="mb-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
    <p class="font-medium text-slate-900">Master mapping structure</p>
    <p class="mt-1">
        <span class="font-semibold">IBS Model</span> (master key)
        <span class="text-slate-400 mx-1">→</span>
        <span class="font-semibold">LK Model</span> (active · current live source)
        <span class="text-slate-400 mx-1">→</span>
        <span class="text-slate-400">SM Model (reserved)</span>
    </p>
    <p class="mt-1 text-xs text-slate-500">Live source: LK (OpenCart via IBS connector) · Warehouse products only · Inspection only</p>
</div>

<div class="mb-4 flex flex-wrap items-center gap-3">
    <form method="POST" action="{{ route('product-map.load') }}">
        @csrf
        <button type="submit"
                @disabled(! ($connectionReady ?? false))
                class="rounded-md px-4 py-2 text-sm font-medium text-white {{ ($connectionReady ?? false) ? 'bg-slate-900 hover:bg-slate-800' : 'bg-slate-300 cursor-not-allowed' }}"
                title="{{ ($connectionReady ?? false) ? 'Load live warehouse products' : 'Save an active connection first' }}">
            Load Products
        </button>
    </form>

    <form method="POST" action="{{ route('product-map.refresh') }}">
        @csrf
        <button type="submit"
                @disabled(! ($connectionReady ?? false) && ! $hasPreview)
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
            Refresh Preview
        </button>
    </form>

    @if ($hasPreview && ($previewSummary ?? null))
        <span class="text-sm text-slate-600">
            {{ count($products) }} warehouse product(s)
            @if (($previewMeta['pages_fetched'] ?? 0) > 0)
                · {{ $previewMeta['pages_fetched'] }} page(s)
            @endif
            @if (($previewMeta['duplicates_skipped'] ?? 0) > 0)
                · {{ $previewMeta['duplicates_skipped'] }} duplicate(s) skipped
            @endif
            · {{ $previewSummary['unique_ibs_models'] ?? 0 }} IBS models
        </span>
    @endif
</div>

@if (! ($connectionReady ?? false))
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Save an active connection in <a href="{{ route('connection.edit') }}" class="font-medium underline">Connection</a> before loading products.
    </div>
@endif

@if ($hasPreview && ($previewSummary ?? null))
    <div class="mb-4 grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Warehouse</p>
            <p class="text-lg font-semibold text-slate-900">{{ $previewSummary['warehouse_preview'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-xs text-slate-500 uppercase tracking-wide">IBS models</p>
            <p class="text-lg font-semibold text-slate-900">{{ $previewSummary['unique_ibs_models'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Health OK</p>
            <p class="text-lg font-semibold text-emerald-700">{{ $previewSummary['health_ok'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Option images</p>
            <p class="text-lg font-semibold text-slate-900">{{ $previewSummary['option_images_count'] ?? 0 }}/{{ $previewSummary['variant_rows'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Variant models</p>
            <p class="text-lg font-semibold text-slate-900">{{ $previewSummary['variant_models_count'] ?? 0 }}/{{ $previewSummary['variant_rows'] ?? 0 }}</p>
        </div>
    </div>
@endif

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm table-compact product-map-table">
            <thead class="bg-slate-50">
                <tr>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">OC Product ID</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Product Image</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">IBS Model</th>
                    <th class="text-left font-medium text-slate-600">Product Name</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Type</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Stock</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Health</th>
                    <th class="col-center font-medium text-slate-600 whitespace-nowrap">Variants / Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $index => $product)
                    @php
                        $health = $product['health'] ?? ['status' => 'ok', 'label' => 'OK', 'issues' => []];
                        $options = $product['options'] ?? $product['variants'] ?? [];
                        $rowId = 'product-row-'.$index;
                        $isVariable = count($options) > 0;
                        $typeLabel = $isVariable ? 'Variable' : 'Simple';
                        $stock = (int) ($product['stock'] ?? 0);
                        $healthIssues = $health['issues'] ?? [];
                        $healthTitle = $healthIssues !== [] ? implode('; ', $healthIssues) : 'No issues';
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="col-center font-mono text-xs text-slate-700 whitespace-nowrap">
                            {{ $product['oc_product_id'] ?? $product['product_id'] ?? '—' }}
                        </td>
                        <td class="col-center">
                            @if (! empty($product['image']))
                                <img src="{{ $product['image'] }}" alt="" class="product-thumb" loading="lazy">
                            @else
                                <span class="inline-flex product-thumb items-center justify-center text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="col-center font-mono text-xs font-semibold text-slate-800 whitespace-nowrap">
                            {{ $product['ibs_model'] ?: '—' }}
                        </td>
                        <td class="text-left font-medium text-slate-900">{{ $product['name'] ?: '—' }}</td>
                        <td class="col-center text-slate-700 whitespace-nowrap">{{ $typeLabel }}</td>
                        <td class="col-center font-medium whitespace-nowrap {{ $stock < 0 ? 'stock-negative' : 'text-slate-900' }}">
                            {{ $stock }}
                        </td>
                        <td class="col-center">
                            @if (($health['status'] ?? '') === 'ok')
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">OK</span>
                            @else
                                <div class="inline-flex flex-col items-center gap-0.5" title="{{ $healthTitle }}">
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Review</span>
                                    <span class="text-[10px] leading-tight text-amber-700 max-w-[8rem] truncate">{{ $healthIssues[0] ?? 'Review' }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="col-center whitespace-nowrap">
                            @if ($isVariable)
                                <button type="button"
                                        class="variants-btn expand-toggle"
                                        data-target="{{ $rowId }}"
                                        data-count="{{ count($options) }}"
                                        aria-expanded="false">
                                    Variants ({{ count($options) }})
                                </button>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @if ($isVariable)
                        <tr id="{{ $rowId }}" class="hidden bg-slate-50/80 variant-row">
                            <td colspan="8" class="px-4 py-4">
                                <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                                    <p class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-100">
                                        Variants · {{ $product['ibs_model'] ?: $product['name'] }}
                                    </p>
                                    <table class="min-w-full text-sm table-compact product-map-table">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <th class="text-left font-medium text-slate-600">Option Name</th>
                                                <th class="text-left font-medium text-slate-600">Option Value</th>
                                                <th class="col-center font-medium text-slate-600">IBS Model</th>
                                                <th class="col-center font-medium text-slate-600">Stock</th>
                                                <th class="col-center font-medium text-slate-600">Option Image</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($options as $option)
                                                @php $optionStock = (int) ($option['stock'] ?? $option['quantity'] ?? 0); @endphp
                                                <tr>
                                                    <td class="text-slate-800">{{ $option['option_name'] ?? '—' }}</td>
                                                    <td class="text-slate-800">{{ $option['option_value'] ?? '—' }}</td>
                                                    <td class="col-center font-mono text-xs font-semibold text-slate-800">{{ $option['ibs_model'] ?? $option['model'] ?? '—' }}</td>
                                                    <td class="col-center font-medium {{ $optionStock < 0 ? 'stock-negative' : 'text-slate-900' }}">{{ $optionStock }}</td>
                                                    <td class="col-center">
                                                        @if (! empty($option['image']))
                                                            <img src="{{ $option['image'] }}" alt="" class="product-thumb" loading="lazy">
                                                        @else
                                                            <span class="text-xs text-slate-400">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-slate-500 py-16 px-4">
                            <p class="font-medium text-slate-700 mb-1">No preview loaded</p>
                            <p class="text-sm">Click <strong>Load Products</strong> to inspect live warehouse products. Nothing is saved to the database.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
    <strong>Step 2A — inspection only.</strong> Preview listing aligned with IBS Product Control. No edit, import, or DB writes.
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('.expand-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-target'));
            if (!target) return;

            var isHidden = target.classList.contains('hidden');
            var count = button.getAttribute('data-count') || '';

            target.classList.toggle('hidden', !isHidden);
            button.classList.toggle('is-open', isHidden);
            button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            button.textContent = isHidden ? 'Hide variants' : 'Variants (' + count + ')';
        });
    });
})();
</script>
@endpush
