<div class="order-map-row-actions">
    <button type="button" class="btn btn-ghost btn-sm" data-order-panel-open data-order-panel-url="{{ route('order-map.panel', $order) }}">View</button>
    <a href="{{ route('order-map.print-invoice', $order) }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">Print</a>
</div>
