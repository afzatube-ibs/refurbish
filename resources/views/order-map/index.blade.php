@extends('layouts.app')

@section('title', 'Order Queue — DropFlow SFM')
@section('page-title', 'Order Queue')
@section('page-subtitle', 'IBS fulfillment workflow — manual import and status sync')

@section('page-actions')
    @if (auth()->user()->isAdmin())
        <a href="{{ route('settings.order-status-mapping.index') }}" class="header-action-btn header-action-btn--secondary">Status Mapping</a>
        <form method="POST" action="{{ route('order-map.load') }}" class="inline">
            @csrf
            <button type="submit" class="header-action-btn header-action-btn--primary">Load New Orders</button>
        </form>
        <form method="POST" action="{{ route('order-map.sync-updates') }}" class="inline">
            @csrf
            <button type="submit" class="header-action-btn header-action-btn--secondary">Sync Status Updates</button>
        </form>
    @endif
@endsection

@section('content')
@include('order-map.partials.filters', ['statusFilter' => $statusFilter ?? null])

<div class="order-map-list-card">
    <div class="order-map-table-wrap">
        <table class="data-table order-map-table">
            @include('order-map.partials.table-colgroup')
            <thead>
                <tr>
                    <th>Order No</th>
                    <th>Customer</th>
                    <th>Product Card</th>
                    <th class="order-map-num">Total Qty</th>
                    <th class="order-map-num">Total Cost</th>
                    <th>IBS Status</th>
                    <th>Consignment ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $index => $order)
                    @php $row = $queueRows[$index] ?? null; @endphp
                    <tr>
                        <td class="order-map-order-no">#{{ $order->source_order_id }}</td>
                        <td class="order-map-customer">
                            <div class="order-map-customer-name">{{ $order->customer_name }}</div>
                            <div class="order-map-customer-phone">{{ $order->customer_phone }}</div>
                        </td>
                        <td class="order-map-product-card">
                            @include('order-map.partials.product-card', [
                                'cards' => $row['product_cards'] ?? [],
                                'hasUnmatched' => $row['has_unmatched'] ?? false,
                            ])
                        </td>
                        <td class="order-map-num">{{ $row['total_qty'] ?? 0 }}</td>
                        <td class="order-map-num">{{ number_format($row['total_cost'] ?? 0, 2) }}</td>
                        <td><x-status-badge :status="$order->sfm_status" /></td>
                        <td class="order-map-consignment">{{ $order->consignment_id ?: '—' }}</td>
                        <td class="order-map-actions">
                            @include('order-map.partials.row-actions', ['order' => $order])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="order-map-empty">No orders in queue yet.@if (auth()->user()->isAdmin()) Use <strong>Load New Orders</strong> after mapping Import Trigger statuses.@endif</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($orders->hasPages())
    <div class="order-map-pagination">{{ $orders->links() }}</div>
@endif
@endsection
