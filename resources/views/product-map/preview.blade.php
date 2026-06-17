@extends('layouts.app')

@section('title', 'Product Map — DropFlow SFM')
@section('page-title', 'Product Map')
@section('page-subtitle', 'DropFlow warehouse catalog — LK snapshot on sync, supplier fields edited inline')

@section('page-badge')
    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ config('dropflow.version', 'v0.6.6') }}</span>
@endsection

@section('page-actions')
@php
    $hasPreview = ! empty($products);
@endphp
<div class="pm-header-actions">
    <div class="pm-action-group" aria-label="Catalog actions">
        <span class="pm-action-group-label">LK source</span>
        <form method="POST" action="{{ route('product-map.load') }}">
            @csrf
            <button type="submit"
                    @disabled(! ($connectionReady ?? false))
                    class="header-action-btn header-action-btn--primary"
                    title="{{ ($connectionReady ?? false) ? 'Fetch Lokkisona snapshot and compare with local DB' : 'Save an active connection first' }}">
                Sync LK Products
            </button>
        </form>
    </div>
    <div class="pm-action-group" aria-label="Local catalog actions">
        <span class="pm-action-group-label">DropFlow DB</span>
        <form method="POST" action="{{ route('product-map.refresh') }}" id="product-map-refresh-form">
            @csrf
            <button type="submit"
                    id="product-map-refresh-btn"
                    @disabled(! $hasPreview)
                    class="header-action-btn header-action-btn--secondary"
                    title="{{ $hasPreview ? 'Reload from database only — no LK connector call' : 'Sync LK products first' }}">
                Refresh Local List
            </button>
        </form>
    </div>
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
        refreshBtn.dataset.originalLabel = refreshBtn.textContent;
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

@if (! $inReview && ! $hasPreview)
    <div class="pm-workflow-hint" role="note">
        <p><strong>1.</strong> Sync LK Products — fetch Lokkisona snapshot into DropFlow.</p>
        <p><strong>2.</strong> Edit supplier fields — rates, stock, models (saved locally).</p>
        <p><strong>3.</strong> Refresh Local List — reload health counts from the database.</p>
        @if (! config('dropflow.product_sync_supports_changed_since', false))
            <p class="pm-workflow-hint-note">Incremental LK sync uses full compare until the connector supports <code>changed_since</code>.</p>
        @endif
    </div>
@elseif ($hasPreview && ! config('dropflow.product_sync_supports_changed_since', false))
    <p class="pm-sync-note" role="note">Incremental LK sync uses full compare until the connector supports <code>changed_since</code>.</p>
@endif

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
