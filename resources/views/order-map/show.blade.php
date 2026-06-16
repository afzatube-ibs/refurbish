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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="order-map-list-card p-6">
            <h2 class="font-medium text-slate-900 mb-4">Customer</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-slate-500">Name</dt>
                    <dd class="font-medium text-slate-900 mt-1">{{ $order->customer_name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Phone</dt>
                    <dd class="font-medium text-slate-900 mt-1">{{ $order->customer_phone }}</dd>
                </div>
            </dl>
        </div>

        <div class="order-map-list-card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 class="font-medium text-slate-900">Product Lines</h2>
            </div>
            <div class="p-4">
                @include('order-map.partials.product-card', [
                    'cards' => $queueRow['product_cards'] ?? [],
                    'hasUnmatched' => $queueRow['has_unmatched'] ?? false,
                ])
            </div>
            <div class="px-6 py-3 border-t border-slate-200 text-sm flex justify-between">
                <span class="text-slate-600">Supplier cost total</span>
                <span class="font-semibold text-slate-900">{{ number_format($queueRow['total_cost'] ?? 0, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        @if (! auth()->user()->isAdmin() && ($availableTransitions ?? []))
            <div class="order-map-list-card p-6">
                <h2 class="font-medium text-slate-900 mb-4">IBS Actions</h2>
                @include('order-map.partials.workflow-actions', ['order' => $order, 'availableTransitions' => $availableTransitions])
            </div>
        @endif

        <div class="order-map-list-card p-6 text-sm space-y-3">
            <div class="flex justify-between"><span class="text-slate-500">Total Qty</span><span>{{ $queueRow['total_qty'] ?? 0 }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Consignment</span><span>{{ $order->consignment_id ?: '—' }}</span></div>
            <a href="{{ route('order-map.print-invoice', $order) }}" class="btn btn-secondary btn-sm w-full text-center" target="_blank" rel="noopener">Print Invoice</a>
        </div>
    </div>
</div>
@endsection
