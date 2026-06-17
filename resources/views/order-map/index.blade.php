@extends('layouts.app')

@section('title', 'Order Queue — DropFlow SFM')
@section('page-title', 'Order Queue')
@section('page-subtitle', 'Supplier order queue and fulfillment workflow')

@section('page-actions')
    @if (auth()->user()->isAdmin())
        <div class="order-map-header-actions">
            <form method="POST" action="{{ route('order-map.load') }}" class="inline">
                @csrf
                <button type="submit" class="header-action-btn header-action-btn--primary">Load Orders</button>
            </form>
            <form method="POST" action="{{ route('order-map.sync-updates') }}" class="inline">
                @csrf
                <button type="submit" class="header-action-btn header-action-btn--secondary">Sync Updates</button>
            </form>
            <a href="{{ route('order-map.create') }}" class="header-action-btn header-action-btn--secondary">Create Order</a>
            <div class="header-more-menu" data-header-more>
                <button type="button" class="header-action-btn header-action-btn--secondary" data-header-more-toggle aria-expanded="false">
                    More <span aria-hidden="true">▾</span>
                </button>
                <div class="header-more-dropdown" data-header-more-menu hidden>
                    <a href="{{ route('settings.order-status-mapping.index') }}" class="header-more-item">Status Mapping</a>
                    <form method="POST" action="{{ route('order-map.audit-connector') }}">
                        @csrf
                        <button type="submit" class="header-more-item header-more-item--button">Audit</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('content')
@include('order-map.partials.filters', ['statusFilter' => $statusFilter ?? null])

@if (session('order_connector_audit_visible') && ! empty($lastConnectorAudit))
    @include('order-map.partials.connector-audit-panel', [
        'lastConnectorAudit' => $lastConnectorAudit,
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
                    <th>LK Status</th>
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
                        <td colspan="9" class="order-map-empty">
                            No orders in queue yet.@if (auth()->user()->isAdmin()) Click Load Orders after mapping import statuses.@endif
                        </td>
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

    function initOrderPanelInteractions(root) {
        if (!root) return;

        root.querySelectorAll('[data-order-edit-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-order-edit-target');
                var form = targetId ? document.getElementById(targetId) : null;
                if (!form) return;
                form.hidden = !form.hidden;
                btn.textContent = form.hidden ? 'Edit Order' : 'Hide Edit Form';
            });
        });

        root.querySelectorAll('[data-dispatch-reveal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-dispatch-target');
                var form = targetId ? document.getElementById(targetId) : null;
                if (!form) return;
                form.hidden = false;
                btn.hidden = true;
                var input = form.querySelector('input[name="consignment_id"]');
                if (input) input.focus();
            });
        });
    }

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
                initOrderPanelInteractions(body);
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

        var moreToggle = e.target.closest('[data-header-more-toggle]');
        if (moreToggle) {
            var menu = moreToggle.closest('[data-header-more]');
            var dropdown = menu ? menu.querySelector('[data-header-more-menu]') : null;
            if (dropdown) {
                var open = dropdown.hasAttribute('hidden');
                dropdown.toggleAttribute('hidden', !open);
                moreToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            return;
        }

        if (!e.target.closest('[data-header-more]')) {
            document.querySelectorAll('[data-header-more-menu]').forEach(function (dropdown) {
                dropdown.setAttribute('hidden', 'hidden');
            });
            document.querySelectorAll('[data-header-more-toggle]').forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
            });
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
