@extends('layouts.app')

@section('title', 'Product Map — DropFlow SFM')
@section('page-title', 'Product Map')
@section('page-subtitle', 'Supplier product catalog and inventory control')

@section('page-badge')
    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ config('dropflow.version', 'v0.6.7') }}</span>
@endsection

@section('page-actions')
@php
    $hasPreview = ! empty($products);
@endphp
<div class="pm-header-actions">
    <form method="POST" action="{{ route('product-map.load') }}">
        @csrf
        <button type="submit"
                @disabled(! ($connectionReady ?? false))
                class="header-action-btn header-action-btn--primary"
                title="{{ ($connectionReady ?? false) ? 'Load products from Lokkisona' : 'Configure connection first' }}">
            Load Products
        </button>
    </form>
    <form method="POST" action="{{ route('product-map.refresh') }}" id="product-map-refresh-form">
        @csrf
        <button type="submit"
                id="product-map-refresh-btn"
                @disabled(! $hasPreview)
                class="header-action-btn header-action-btn--secondary"
                title="{{ $hasPreview ? 'Refresh catalog from local database' : 'Load products first' }}">
            Refresh
        </button>
    </form>
</div>
@push('scripts')
<script>
(function () {
    var refreshForm = document.getElementById('product-map-refresh-form');
    var refreshBtn = document.getElementById('product-map-refresh-btn');
    if (!refreshForm || !refreshBtn) return;
    refreshForm.addEventListener('submit', function () {
        if (refreshBtn.disabled) return;
        refreshBtn.disabled = true;
        refreshBtn.textContent = 'Refreshing…';
        refreshBtn.classList.add('is-loading');
    });
})();
</script>
@endpush
@endsection

@section('content')
@php
    $hasPreview = ! empty($products);
    $pendingLoad = is_array($pendingLoad ?? null) ? $pendingLoad : null;
    $pendingProducts = is_array($pendingProducts ?? null) ? $pendingProducts : [];
    $pendingCount = (int) ($pendingLoad['count'] ?? count($pendingProducts));
    $inReview = $pendingCount > 0 && $pendingProducts !== [];
@endphp

@if ($inReview)
    @include('product-map.partials.pending-load-review', [
        'pendingProducts' => $pendingProducts,
        'pendingCount' => $pendingCount,
        'isFirstLoad' => ! $hasPreview,
    ])
@else
    @if ($hasPreview && ($previewSummary ?? null))
        @include('product-map.partials.summary')
    @endif

    @if ($hasPreview)
        @include('product-map.partials.filters')
    @endif

    @include('product-map.partials.table-listing')

    @if ($hasPreview)
        @php
            $stockReasons = \App\Services\ProductMap\ProductMapLocalControlService::STOCK_REASONS;
            $productCategories = app(\App\Services\ProductMap\ProductControlCategoryService::class)->categoriesForSupplier();
        @endphp
        @include('product-map.partials.control-modal', ['stockReasons' => $stockReasons])
        @include('product-map.partials.control-scripts', [
            'stockReasons' => $stockReasons,
            'productCategories' => $productCategories ?? [],
        ])
    @endif
@endif
@endsection
