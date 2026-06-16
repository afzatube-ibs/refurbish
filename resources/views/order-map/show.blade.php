@extends('layouts.app')

@section('title', 'Order #' . $order->source_order_id . ' — DropFlow SFM')
@section('page-title', 'Order #' . $order->source_order_id)
@section('page-subtitle', $order->customer_name . ' · ' . $order->customer_phone)

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-3">
    <x-status-badge :status="$order->sfm_status" />
    @if ($queueRow['has_unmatched'] ?? false)
        <span class="order-map-unmatched-badge">Unmatched products</span>
    @endif
    <a href="{{ route('order-map.index') }}" class="ml-auto text-sm text-slate-600 hover:text-slate-900 underline">← Back to queue</a>
</div>

@include('order-map.partials.detail-panel', [
    'order' => $order,
    'queueRow' => $queueRow,
    'availableTransitions' => $availableTransitions ?? [],
    'canEdit' => $canEdit ?? false,
])
@endsection
