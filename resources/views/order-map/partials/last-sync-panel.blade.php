@php
    /** @var \App\Services\OrderMap\OrderMapLoadLogService $loadLogService */
    $skipLog = is_array($lastSync['skip_log'] ?? null) ? $lastSync['skip_log'] : [];
    $connectorOrders = is_array($lastSync['connector_orders'] ?? null) ? $lastSync['connector_orders'] : [];
@endphp

@if (($lastSync['mode'] ?? '') === 'import' || ($lastSync['mode'] ?? '') === 'update')
    <section class="order-map-sync-panel" aria-label="Last order sync details">
        <div class="order-map-sync-summary">
            <h2 class="order-map-sync-title">
                {{ ($lastSync['mode'] ?? '') === 'update' ? 'Last Sync Status Updates' : 'Last Load New Orders' }}
            </h2>
            @if (! empty($lastSync['recorded_at']))
                <p class="order-map-sync-time">{{ \Carbon\Carbon::parse($lastSync['recorded_at'])->format('d M Y H:i') }}</p>
            @endif
            <dl class="order-map-sync-stats">
                <div><dt>Fetched from OC</dt><dd>{{ (int) ($lastSync['fetched'] ?? 0) }}</dd></div>
                @if (($lastSync['mode'] ?? '') === 'import')
                    <div><dt>Imported</dt><dd>{{ (int) ($lastSync['imported'] ?? 0) }}</dd></div>
                    <div><dt>Duplicates skipped</dt><dd>{{ (int) ($lastSync['duplicates_skipped'] ?? 0) }}</dd></div>
                    <div><dt>Update-only skipped</dt><dd>{{ (int) ($lastSync['update_only_skipped'] ?? 0) }}</dd></div>
                    <div><dt>Unmatched product lines</dt><dd>{{ (int) ($lastSync['unmatched_lines'] ?? 0) }}</dd></div>
                @else
                    <div><dt>Updated</dt><dd>{{ (int) ($lastSync['updated'] ?? 0) }}</dd></div>
                    <div><dt>Not found skipped</dt><dd>{{ (int) ($lastSync['not_found_skipped'] ?? 0) }}</dd></div>
                    <div><dt>Locked skipped</dt><dd>{{ (int) ($lastSync['locked_skipped'] ?? 0) }}</dd></div>
                @endif
                <div><dt>Requested status_ids</dt>
                    <dd>
                        @if (! empty($lastSync['requested_status_ids']))
                            [{{ implode(', ', array_map('intval', $lastSync['requested_status_ids'])) }}]
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div><dt>Connector returned</dt><dd>{{ (int) ($lastSync['connector_raw_count'] ?? $lastSync['fetched'] ?? 0) }} orders</dd></div>
                <div><dt>Connector total</dt><dd>{{ (int) ($lastSync['connector_total'] ?? $lastSync['connector_raw_count'] ?? 0) }}</dd></div>
                <div><dt>Pages fetched</dt><dd>{{ (int) ($lastSync['pages_fetched'] ?? 1) }}</dd></div>
                @if (! empty($lastSync['filter_applied']))
                    <div><dt>Filter applied</dt><dd>{{ $lastSync['filter_applied'] }}</dd></div>
                @endif
            </dl>
        </div>

        @if ($connectorOrders !== [])
            <details class="order-map-sync-details">
                <summary>Connector response ({{ count($connectorOrders) }} orders)</summary>
                <div class="order-map-sync-table-wrap">
                    <table class="data-table order-map-sync-table">
                        <thead>
                            <tr>
                                <th>Order No</th>
                                <th>OC Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($connectorOrders as $row)
                                <tr>
                                    <td>#{{ $row['order_id'] ?? '—' }}</td>
                                    <td>{{ $loadLogService->formatOcStatusLabel((int) ($row['order_status_id'] ?? 0), (string) ($row['order_status_name'] ?? '')) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        @if ($skipLog !== [])
            <div class="order-map-skipped-panel">
                <h3 class="order-map-skipped-title">Skipped orders ({{ count($skipLog) }})</h3>
                <div class="order-map-sync-table-wrap">
                    <table class="data-table order-map-sync-table">
                        <thead>
                            <tr>
                                <th>Order No</th>
                                <th>OC Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($skipLog as $entry)
                                <tr @class(['order-map-skip-bug' => ($entry['reason'] ?? '') === 'product_unmatched'])>
                                    <td>#{{ $entry['order_id'] ?? '—' }}</td>
                                    <td>{{ $loadLogService->formatOcStatusLabel((int) ($entry['oc_status_id'] ?? 0), (string) ($entry['oc_status_name'] ?? '')) }}</td>
                                    <td>{{ $loadLogService->formatSkipReason((string) ($entry['reason'] ?? ''), (string) ($entry['detail'] ?? '')) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>
@endif
