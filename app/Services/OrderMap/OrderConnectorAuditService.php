<?php

namespace App\Services\OrderMap;

use App\Models\Connection;
use App\Models\OrderStatusMapping;
use App\Models\User;
use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\OpenCartHttpClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderConnectorAuditService
{
    public const SESSION_KEY = 'order_map_last_connector_audit';

    public function __construct(
        private readonly ConnectionService $connectionService,
    ) {}

    /**
     * Run connector orders_audit for Import Trigger status mappings.
     *
     * @return array<string, mixed>
     */
    public function runImportTriggerAudit(?User $user = null): array
    {
        $this->connectionService->assertSyncAllowed();

        $statusIds = OrderStatusMapping::query()
            ->importTrigger()
            ->pluck('source_status_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($statusIds === []) {
            throw new RuntimeException('No Import Trigger statuses mapped. Configure Order Status Mapping first.');
        }

        $connection = Connection::getInstance();
        $client = new OpenCartHttpClient($connection);

        $audit = $client->runOrdersAudit($statusIds);
        $ordersProbe = $client->get($connection->order_api_endpoint, [
            'status_ids' => $statusIds,
            'page' => 1,
            'limit' => (int) config('dropflow.order_import_page_size', 20),
        ]);

        $summary = [
            'requested_status_ids' => $statusIds,
            'connector_version' => (string) ($audit['connector_version'] ?? ''),
            'connector_build' => (string) ($audit['connector_build'] ?? ''),
            'orders_filter_mode' => (string) ($audit['orders_filter_mode'] ?? ''),
            'audit_route' => (string) ($audit['_audit_route'] ?? $audit['route'] ?? ''),
            'total_raw_orders' => (int) ($audit['total_raw_orders'] ?? 0),
            'total_after_filter' => (int) ($audit['total_after_filter'] ?? 0),
            'total_returned_this_page' => (int) ($audit['total_returned_this_page'] ?? 0),
            'orders_api_total' => (int) ($ordersProbe['total'] ?? 0),
            'orders_api_returned_page' => is_array($ordersProbe['orders'] ?? null) ? count($ordersProbe['orders']) : 0,
            'raw_order_ids' => is_array($audit['raw_order_ids'] ?? null) ? $audit['raw_order_ids'] : [],
            'filtered_order_ids' => is_array($audit['filtered_order_ids'] ?? null) ? $audit['filtered_order_ids'] : [],
            'returned_order_ids' => is_array($audit['returned_order_ids'] ?? null) ? $audit['returned_order_ids'] : [],
            'status_breakdown' => is_array($audit['status_breakdown'] ?? null) ? $audit['status_breakdown'] : [],
            'excluded_order_ids' => is_array($audit['excluded_order_ids'] ?? null) ? $audit['excluded_order_ids'] : [],
            'would_exclude_if_warehouse_bridge' => is_array($audit['diagnostics']['would_exclude_if_warehouse_bridge'] ?? null)
                ? $audit['diagnostics']['would_exclude_if_warehouse_bridge']
                : [],
            'orders_without_line_items' => is_array($audit['diagnostics']['orders_without_line_items'] ?? null)
                ? $audit['diagnostics']['orders_without_line_items']
                : [],
            'audit_note' => (string) ($audit['audit_note'] ?? ''),
            'page' => (int) ($audit['page'] ?? 1),
            'limit' => (int) ($audit['limit'] ?? 20),
            'recorded_at' => now()->toIso8601String(),
            'triggered_by' => $user?->id,
        ];

        session()->put(self::SESSION_KEY, $summary);
        session()->flash('logs_tab', 'current');

        Log::info('order_map.connector_audit', $summary);

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function last(): array
    {
        $audit = session(self::SESSION_KEY);

        return is_array($audit) ? $audit : [];
    }

    /**
     * @param  array<string, mixed>  $audit
     */
    public function formatBannerMessage(array $audit): string
    {
        $statusIds = $audit['requested_status_ids'] ?? [];
        $statusLabel = is_array($statusIds) && $statusIds !== []
            ? '['.implode(', ', array_map('intval', $statusIds)).']'
            : '—';

        return sprintf(
            'Connector audit — status_ids %s: raw=%d, after_filter=%d, orders API total=%d, build=%s',
            $statusLabel,
            (int) ($audit['total_raw_orders'] ?? 0),
            (int) ($audit['total_after_filter'] ?? 0),
            (int) ($audit['orders_api_total'] ?? 0),
            $audit['connector_build'] ?? 'unknown'
        );
    }
}
