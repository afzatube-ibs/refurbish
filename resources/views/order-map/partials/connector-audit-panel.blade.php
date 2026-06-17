@php
    $warehouse = is_array($lastConnectorAudit['would_exclude_if_warehouse_bridge'] ?? null)
        ? $lastConnectorAudit['would_exclude_if_warehouse_bridge']
        : [];
    $emptyLines = is_array($lastConnectorAudit['orders_without_line_items'] ?? null)
        ? $lastConnectorAudit['orders_without_line_items']
        : [];
    $rawIds = is_array($lastConnectorAudit['raw_order_ids'] ?? null) ? $lastConnectorAudit['raw_order_ids'] : [];
    $returnedIds = is_array($lastConnectorAudit['returned_order_ids'] ?? null) ? $lastConnectorAudit['returned_order_ids'] : [];
    $statusBreakdown = is_array($lastConnectorAudit['status_breakdown'] ?? null) ? $lastConnectorAudit['status_breakdown'] : [];
    $excluded = is_array($lastConnectorAudit['excluded_order_ids'] ?? null) ? $lastConnectorAudit['excluded_order_ids'] : [];
@endphp

@if (! empty($lastConnectorAudit))
    <section class="order-map-sync-panel order-map-audit-panel" aria-label="Connector orders audit">
        <div class="order-map-sync-summary">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="order-map-sync-title">Connector Orders Audit</h2>
                    @if (! empty($lastConnectorAudit['recorded_at']))
                        <p class="order-map-sync-time">{{ \Carbon\Carbon::parse($lastConnectorAudit['recorded_at'])->format('d M Y H:i') }}</p>
                    @endif
                </div>
                <a href="{{ route('order-map.index', ['dismiss_audit' => 1]) }}" class="header-action-btn header-action-btn--secondary">Dismiss</a>
            </div>
            <dl class="order-map-sync-stats">
                <div><dt>Connector build</dt><dd>{{ $lastConnectorAudit['connector_build'] ?? '—' }}</dd></div>
                <div><dt>Connector version</dt><dd>{{ $lastConnectorAudit['connector_version'] ?? '—' }}</dd></div>
                <div><dt>Audit route</dt><dd class="break-all">{{ $lastConnectorAudit['audit_route'] ?? '—' }}</dd></div>
                <div><dt>Filter mode</dt><dd>{{ $lastConnectorAudit['orders_filter_mode'] ?? '—' }}</dd></div>
                <div><dt>Requested status_ids</dt>
                    <dd>
                        @if (! empty($lastConnectorAudit['requested_status_ids']))
                            [{{ implode(', ', array_map('intval', $lastConnectorAudit['requested_status_ids'])) }}]
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt>total_raw_orders</dt><dd>{{ (int) ($lastConnectorAudit['total_raw_orders'] ?? 0) }}</dd></div>
                <div><dt>total_after_filter</dt><dd>{{ (int) ($lastConnectorAudit['total_after_filter'] ?? 0) }}</dd></div>
                <div><dt>Orders API total</dt><dd>{{ (int) ($lastConnectorAudit['orders_api_total'] ?? 0) }}</dd></div>
                <div><dt>Returned this page</dt><dd>{{ (int) ($lastConnectorAudit['total_returned_this_page'] ?? 0) }}</dd></div>
                <div><dt>Orders API page count</dt><dd>{{ (int) ($lastConnectorAudit['orders_api_returned_page'] ?? 0) }}</dd></div>
            </dl>
            @if (! empty($lastConnectorAudit['audit_note']))
                <p class="mt-3 text-xs text-slate-500">{{ $lastConnectorAudit['audit_note'] }}</p>
            @endif
        </div>

        @if ($statusBreakdown !== [])
            <details class="order-map-sync-details" open>
                <summary>status_breakdown</summary>
                <div class="order-map-sync-table-wrap">
                    <table class="data-table order-map-sync-table">
                        <thead>
                            <tr>
                                <th>Status ID</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statusBreakdown as $statusId => $count)
                                <tr>
                                    <td>#{{ $statusId }}</td>
                                    <td>{{ (int) $count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        @if ($rawIds !== [])
            <details class="order-map-sync-details">
                <summary>raw_order_ids ({{ count($rawIds) }})</summary>
                <p class="text-xs text-slate-600 break-all px-1 py-2">{{ implode(', ', array_map('intval', $rawIds)) }}</p>
            </details>
        @endif

        @if ($returnedIds !== [])
            <details class="order-map-sync-details">
                <summary>returned_order_ids ({{ count($returnedIds) }})</summary>
                <p class="text-xs text-slate-600 break-all px-1 py-2">{{ implode(', ', array_map('intval', $returnedIds)) }}</p>
            </details>
        @endif

        @if ($warehouse !== [])
            <details class="order-map-sync-details">
                <summary>would_exclude_if_warehouse_bridge (diagnostic only)</summary>
                <div class="p-2 text-xs text-slate-600 space-y-1">
                    <p>Count: {{ (int) ($warehouse['count'] ?? 0) }}</p>
                    @if (! empty($warehouse['note']))
                        <p>{{ $warehouse['note'] }}</p>
                    @endif
                    @if (! empty($warehouse['order_ids']) && is_array($warehouse['order_ids']))
                        <p class="break-all">Order IDs: {{ implode(', ', array_map('intval', $warehouse['order_ids'])) }}</p>
                    @endif
                </div>
            </details>
        @endif

        @if (($emptyLines['count'] ?? 0) > 0)
            <details class="order-map-sync-details">
                <summary>orders_without_line_items ({{ (int) $emptyLines['count'] }})</summary>
                @if (! empty($emptyLines['order_ids']) && is_array($emptyLines['order_ids']))
                    <p class="text-xs text-slate-600 break-all px-1 py-2">{{ implode(', ', array_map('intval', $emptyLines['order_ids'])) }}</p>
                @endif
            </details>
        @endif

        @if ($excluded !== [])
            <details class="order-map-sync-details">
                <summary>excluded_order_ids ({{ count($excluded) }})</summary>
                <div class="order-map-sync-table-wrap">
                    <table class="data-table order-map-sync-table">
                        <thead>
                            <tr>
                                <th>Order No</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($excluded as $entry)
                                <tr>
                                    <td>#{{ $entry['order_id'] ?? '—' }}</td>
                                    <td>{{ $entry['reason'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </section>
@endif
