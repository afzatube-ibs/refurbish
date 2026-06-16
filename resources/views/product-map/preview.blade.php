@extends('layouts.app')

@section('title', 'Product Map — DropFlow SFM')
@section('page-title', 'Product Map')
@section('page-subtitle', 'Product Mapping Center')

@section('page-actions')
@php
    $hasPreview = ! empty($products);
@endphp
<form method="POST" action="{{ route('product-map.load') }}">
    @csrf
    <button type="submit"
            @disabled(! ($connectionReady ?? false))
            class="header-action-btn header-action-btn--primary"
            title="{{ ($connectionReady ?? false) ? 'Load live warehouse products' : 'Save an active connection first' }}">
        Load Products
    </button>
</form>

<form method="POST" action="{{ route('product-map.refresh') }}" id="product-map-refresh-form"
      data-has-local-edits="{{ ($previewMeta['has_local_edits'] ?? false) ? '1' : '0' }}">
    @csrf
    <button type="submit"
            @disabled(! ($connectionReady ?? false) && ! $hasPreview)
            class="header-action-btn header-action-btn--secondary"
            title="Reload the live product preview">
        Refresh Preview
    </button>
</form>
@push('scripts')
<script>
(function () {
    var refreshForm = document.getElementById('product-map-refresh-form');
    if (!refreshForm) return;
    refreshForm.addEventListener('submit', function (e) {
        if (refreshForm.getAttribute('data-has-local-edits') !== '1') return;
        if (!window.confirm('Refresh reloads live stock from OpenCart. Local rate/stock edits are kept. Continue?')) {
            e.preventDefault();
        }
    });
})();
</script>
@endpush
@endsection

@section('content')
@php
    $hasPreview = ! empty($products);
@endphp

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
@endsection
