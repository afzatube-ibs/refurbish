@extends('layouts.app')

@section('title', 'Order Queue — DropFlow SFM')
@section('page-title', 'Order Queue')
@section('page-subtitle', 'IBS fulfillment workflow — manual import and status sync')

@section('page-actions')
    @if (auth()->user()->isAdmin())
        <a href="{{ route('order-map.create') }}" class="header-action-btn header-action-btn--secondary">Create Manual Order</a>
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

@if (! empty($lastSync))
    @include('order-map.partials.last-sync-panel', [
        'lastSync' => $lastSync,
        'loadLogService' => $loadLogService,
    ])
@endif

<div class="order-map-list-card">
    <div class="order-map-table-wrap">
        <table class="data-table order-map-table">
            @include('order-map.partials.table-colgroup')
            <thead>
                <tr>
                    <th>Order No</th>
                    <th>Customer</th>
                    <th>OC Status</th>
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
                        <td class="order-map-oc-status">{{ $row['oc_status_label'] ?? '—' }}</td>
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
                        <td colspan="9" class="order-map-empty">No orders in queue yet.@if (auth()->user()->isAdmin()) Use <strong>Load New Orders</strong> after mapping Import Trigger statuses.@endif</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($orders->hasPages())
    <div class="order-map-pagination">{{ $orders->links() }}</div>
@endif

<div class="modal-overlay" id="orderMapPanelModal" hidden aria-hidden="true">
    <div class="modal-panel order-map-panel-modal" role="dialog" aria-labelledby="orderMapPanelTitle" aria-modal="true">
        <div class="order-map-panel-modal-header">
            <h2 class="order-map-panel-modal-title" id="orderMapPanelTitle">Order details</h2>
            <button type="button" class="modal-close" data-close-order-panel aria-label="Close">&times;</button>
        </div>
        <div class="order-map-panel-modal-body" id="orderMapPanelBody">
            <p class="text-sm text-slate-500">Loading…</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('orderMapPanelModal');
    var body = document.getElementById('orderMapPanelBody');
    var title = document.getElementById('orderMapPanelTitle');

    if (!modal || !body) return;

    function openPanel(url) {
        body.innerHTML = '<p class="text-sm text-slate-500">Loading…</p>';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Failed to load order');
                return response.text();
            })
            .then(function (html) {
                body.innerHTML = html;
                var header = body.querySelector('.order-map-detail-header h2');
                if (header && title) {
                    title.textContent = header.textContent.trim();
                }
            })
            .catch(function () {
                body.innerHTML = '<p class="text-sm text-red-700">Could not load order details.</p>';
            });
    }

    function closePanel() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        body.innerHTML = '';
    }

    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-order-panel-open]');
        if (openBtn) {
            e.preventDefault();
            var url = openBtn.getAttribute('data-order-panel-url');
            if (url) openPanel(url);
            return;
        }

        if (e.target.closest('[data-close-order-panel]')) {
            e.preventDefault();
            closePanel();
            return;
        }

        if (e.target === modal) {
            closePanel();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closePanel();
        }
    });
})();
</script>
@endpush
@endsection
