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

<form method="POST" action="{{ route('product-map.refresh') }}">
    @csrf
    <button type="submit"
            @disabled(! ($connectionReady ?? false) && ! $hasPreview)
            class="header-action-btn header-action-btn--secondary"
            title="Reload the live product preview">
        Refresh Preview
    </button>
</form>
@endsection

@section('content')
@php
    $hasPreview = ! empty($products);
@endphp

@if ($hasPreview && ($previewSummary ?? null))
    @include('product-map.partials.summary')
@endif

@include('product-map.partials.table-listing')

@if ($hasPreview)
    @php
        $stockReasons = \App\Services\ProductMap\ProductMapLocalControlService::STOCK_REASONS;
    @endphp
    @include('product-map.partials.control-modal', ['stockReasons' => $stockReasons])
    @include('product-map.partials.control-scripts', [
        'stockReasons' => $stockReasons,
        'previewActivity' => $previewActivity ?? [],
    ])
@endif
@endsection
