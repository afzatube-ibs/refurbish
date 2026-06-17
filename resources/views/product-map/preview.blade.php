@extends('layouts.app')

@section('title', 'Product Map — DropFlow SFM')
@section('page-title', 'Product Map')
@section('page-subtitle', 'Product Mapping Center')

@section('page-badge')
    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ config('dropflow.version', 'v0.6.3') }}</span>
@endsection

@section('page-actions')
@php
    $hasPreview = ! empty($products);
@endphp
<form method="POST" action="{{ route('product-map.load') }}">
    @csrf
    <button type="submit"
            @disabled(! ($connectionReady ?? false))
            class="header-action-btn header-action-btn--primary"
            title="{{ ($connectionReady ?? false) ? 'Fetch LK product snapshot' : 'Save an active connection first' }}">
        Sync LK Products
    </button>
</form>

<form method="POST" action="{{ route('product-map.refresh') }}" id="product-map-refresh-form">
    @csrf
    <button type="submit"
            @disabled(! $hasPreview)
            class="header-action-btn header-action-btn--secondary"
            title="{{ $hasPreview ? 'Reload products from DropFlow database' : 'Sync products first' }}">
        Refresh Local List
    </button>
</form>
@push('scripts')
<script>
(function () {
    var refreshForm = document.getElementById('product-map-refresh-form');
    if (!refreshForm) return;
    refreshForm.addEventListener('submit', function () {
        // Local DB reload only — no confirmation required.
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
