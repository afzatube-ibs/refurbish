<?php

namespace App\Services\OpenCart;

use App\Enums\OrderItemStatus;
use App\Enums\OrderSyncRole;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusMapping;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderMap\OrderMapLoadLogService;
use App\Services\OrderMap\OrderMapProductMatcher;
use App\Services\OrderMap\OrderMapStockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderSyncService
{
    public function __construct(
        protected OpenCartHttpClient $client,
        protected OrderStatusService $orderStatusService,
        protected OrderMapProductMatcher $productMatcher,
        protected OrderMapStockService $stockService,
        protected OrderMapLoadLogService $loadLogService,
    ) {}

    /**
     * Load new IBS orders — import_trigger mappings only.
     *
     * @return array{
     *     mode: string,
     *     fetched: int,
     *     imported: int,
     *     duplicates_skipped: int,
     *     unmatched_lines: int,
     *     skip_log: list<array{order_id: string, reason: string, detail: string}>
     * }
     */
    public function loadNewOrders(?User $user = null): array
    {
        return $this->runImportSync($user);
    }

    /**
     * @deprecated Use loadNewOrders()
     *
     * @return array<string, mixed>
     */
    public function load(?User $user = null): array
    {
        return $this->loadNewOrders($user);
    }

    /**
     * Sync status updates — update_existing mappings only.
     *
     * @return array{
     *     mode: string,
     *     fetched: int,
     *     updated: int,
     *     not_found_skipped: int,
     *     locked_skipped: int,
     *     skip_log: list<array{order_id: string, reason: string, detail: string}>
     * }
     */
    public function syncStatusUpdates(?User $user = null): array
    {
        return $this->runUpdateSync($user);
    }

    /**
     * @deprecated Use loadNewOrders() or syncStatusUpdates()
     *
     * @return array<string, mixed>
     */
    public function sync(?User $user = null): array
    {
        return $this->loadNewOrders($user);
    }

    /**
     * @return array{
     *     mode: string,
     *     fetched: int,
     *     imported: int,
     *     duplicates_skipped: int,
     *     unmatched_lines: int,
     *     skip_log: list<array{order_id: string, reason: string, detail: string}>
     * }
     */
    protected function runImportSync(?User $user): array
    {
        app(ConnectionService::class)->assertSyncAllowed();
        $connection = Connection::getInstance();
        $supplier = $this->resolveSupplier($connection);
        $user = $this->resolveUser($user);

        $fetchResult = $this->fetchOrdersForImport();
        $orders = $fetchResult['orders'];
        $requestedStatusIds = $fetchResult['requested_status_ids'];

        $fetched = count($orders);
        $imported = 0;
        $duplicatesSkipped = 0;
        $updateOnlySkipped = 0;
        $unmatchedLines = 0;
        $skipLog = [];

        foreach ($orders as $orderData) {
            $normalized = $this->normalizeOrderPayload($orderData);
            $sourceOrderId = $normalized['source_order_id'];
            $ocStatusId = (int) $normalized['current_oc_status_id'];
            $ocStatusName = (string) $normalized['current_oc_status'];

            if ($sourceOrderId === '') {
                $this->recordSkip($skipLog, '—', 'missing_order_id', 'Order payload missing source order id.', 'import', $ocStatusId, $ocStatusName);

                continue;
            }

            if ($requestedStatusIds !== [] && ! in_array($ocStatusId, $requestedStatusIds, true)) {
                $updateOnlySkipped++;
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'update_only_status',
                    'Update-only status; not eligible for import.',
                    'import',
                    $ocStatusId,
                    $ocStatusName
                );

                continue;
            }

            $mapping = $this->orderStatusService->findMapping($ocStatusId);

            if (! $mapping || $mapping->effectiveSyncRole() !== OrderSyncRole::ImportTrigger) {
                $updateOnlySkipped++;
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'not_import_trigger',
                    sprintf('OC status %d is not configured as Import Trigger.', $ocStatusId),
                    'import',
                    $ocStatusId,
                    $ocStatusName
                );

                continue;
            }

            if ($mapping->sfm_status !== SfmOrderStatus::New) {
                $updateOnlySkipped++;
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'invalid_import_mapping',
                    'Import Trigger requires IBS Status New.',
                    'import',
                    $ocStatusId,
                    $ocStatusName
                );

                continue;
            }

            $existing = Order::query()
                ->where('source_order_id', $sourceOrderId)
                ->first();

            if ($existing) {
                $duplicatesSkipped++;
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'duplicate_existing',
                    'Order already exists in IBS queue.',
                    'import',
                    $ocStatusId,
                    $ocStatusName
                );

                continue;
            }

            $order = $this->importOrder($supplier, $normalized, SfmOrderStatus::New, $user);
            $imported++;
            $unmatchedLines += $order->items()->where('is_unmatched', true)->count();
        }

        $summary = [
            'mode' => 'import',
            'fetched' => $fetched,
            'imported' => $imported,
            'duplicates_skipped' => $duplicatesSkipped,
            'update_only_skipped' => $updateOnlySkipped,
            'unmatched_lines' => $unmatchedLines,
            'requested_status_ids' => $requestedStatusIds,
            'connector_raw_count' => $fetchResult['raw_orders_count'],
            'connector_total' => $fetchResult['connector_total'] ?? $fetchResult['raw_orders_count'],
            'pages_fetched' => $fetchResult['pages_fetched'] ?? 1,
            'filter_applied' => $fetchResult['filter_applied'] ?? null,
            'connector_orders' => $fetchResult['connector_orders'],
            'skip_log' => $skipLog,
        ];

        $this->loadLogService->record($summary);
        Log::info('order_map.import.complete', $summary);

        return $summary;
    }

    /**
     * @return array{
     *     mode: string,
     *     fetched: int,
     *     updated: int,
     *     not_found_skipped: int,
     *     locked_skipped: int,
     *     skip_log: list<array{order_id: string, reason: string, detail: string}>
     * }
     */
    protected function runUpdateSync(?User $user): array
    {
        app(ConnectionService::class)->assertSyncAllowed();
        $user = $this->resolveUser($user);

        $fetchResult = $this->fetchOrdersForUpdate();
        $orders = $fetchResult['orders'];

        $fetched = count($orders);
        $updated = 0;
        $notFoundSkipped = 0;
        $lockedSkipped = 0;
        $skipLog = [];

        foreach ($orders as $orderData) {
            $normalized = $this->normalizeOrderPayload($orderData);
            $sourceOrderId = $normalized['source_order_id'];
            $ocStatusId = (int) $normalized['current_oc_status_id'];
            $ocStatusName = (string) $normalized['current_oc_status'];

            if ($sourceOrderId === '') {
                $this->recordSkip($skipLog, '—', 'missing_order_id', 'Order payload missing source order id.', 'update', $ocStatusId, $ocStatusName);

                continue;
            }

            $mapping = $this->orderStatusService->findMapping($ocStatusId);

            if (! $mapping || $mapping->effectiveSyncRole() !== OrderSyncRole::UpdateExisting) {
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'not_update_existing',
                    sprintf('OC status %d is not configured as Update Existing Only.', $ocStatusId),
                    'update',
                    $ocStatusId,
                    $ocStatusName
                );

                continue;
            }

            $mappedStatus = $mapping->sfm_status ?? SfmOrderStatus::Ignore;

            if ($mappedStatus === SfmOrderStatus::Ignore) {
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'ignored_mapping',
                    'Mapped IBS status is Ignore.',
                    'update',
                    $ocStatusId,
                    $ocStatusName
                );

                continue;
            }

            $existing = Order::query()
                ->where('source_order_id', $sourceOrderId)
                ->first();

            if (! $existing) {
                $notFoundSkipped++;
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'update_only_not_found',
                    'Update-only status; order not found in IBS.',
                    'update',
                    $ocStatusId,
                    $ocStatusName
                );

                continue;
            }

            if (! $this->canApplyStatusUpdate($existing, $mappedStatus)) {
                $lockedSkipped++;
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'locked_status',
                    sprintf('Order is locked at %s and cannot be updated from source.', $existing->sfm_status?->label() ?? 'unknown'),
                    'update',
                    $ocStatusId,
                    $ocStatusName
                );

                continue;
            }

            $previousStatus = $existing->sfm_status ?? SfmOrderStatus::New;
            $this->syncExistingOrder($existing, $normalized, $mappedStatus, true);
            $this->applyStatusUpdateStockEffects($existing->fresh(), $previousStatus, $mappedStatus, $user);
            $updated++;
        }

        $summary = [
            'mode' => 'update',
            'fetched' => $fetched,
            'updated' => $updated,
            'not_found_skipped' => $notFoundSkipped,
            'locked_skipped' => $lockedSkipped,
            'requested_status_ids' => $fetchResult['requested_status_ids'],
            'connector_raw_count' => $fetchResult['raw_orders_count'],
            'connector_total' => $fetchResult['connector_total'] ?? $fetchResult['raw_orders_count'],
            'pages_fetched' => $fetchResult['pages_fetched'] ?? 1,
            'filter_applied' => $fetchResult['filter_applied'] ?? null,
            'connector_orders' => $fetchResult['connector_orders'],
            'skip_log' => $skipLog,
        ];

        $this->loadLogService->record($summary);
        Log::info('order_map.update.complete', $summary);

        return $summary;
    }

    /**
     * @return array{
     *     orders: list<array<string, mixed>>,
     *     requested_status_ids: list<int>,
     *     raw_orders_count: int,
     *     connector_orders: list<array{order_id: string, order_status_id: int, order_status_name: string}>
     * }
     */
    protected function fetchOrdersForImport(): array
    {
        $statusIds = OrderStatusMapping::query()
            ->importTrigger()
            ->pluck('source_status_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $this->fetchOrdersByStatusIds($statusIds, 'import');
    }

    /**
     * @return array{
     *     orders: list<array<string, mixed>>,
     *     requested_status_ids: list<int>,
     *     raw_orders_count: int,
     *     connector_orders: list<array{order_id: string, order_status_id: int, order_status_name: string}>
     * }
     */
    protected function fetchOrdersForUpdate(): array
    {
        $statusIds = OrderStatusMapping::query()
            ->updateExisting()
            ->pluck('source_status_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $this->fetchOrdersByStatusIds($statusIds, 'update');
    }

    /**
     * @param  list<int>  $statusIds
     * @return array{
     *     orders: list<array<string, mixed>>,
     *     requested_status_ids: list<int>,
     *     raw_orders_count: int,
     *     connector_total: int,
     *     pages_fetched: int,
     *     filter_applied: ?string,
     *     connector_orders: list<array{order_id: string, order_status_id: int, order_status_name: string}>
     * }
     */
    protected function fetchOrdersByStatusIds(array $statusIds, string $mode): array
    {
        $statusIds = array_values(array_unique(array_filter(
            array_map('intval', $statusIds),
            fn (int $id) => $id > 0
        )));

        if ($statusIds === []) {
            Log::info('order_map.sync.fetch_skipped', [
                'mode' => $mode,
                'reason' => 'no_matching_status_ids',
            ]);

            return [
                'orders' => [],
                'requested_status_ids' => [],
                'raw_orders_count' => 0,
                'connector_total' => 0,
                'pages_fetched' => 0,
                'filter_applied' => null,
                'connector_orders' => [],
            ];
        }

        $connection = Connection::getInstance();
        $pageSize = max(1, (int) config('dropflow.order_import_page_size', 20));
        $maxPages = 50;
        $page = 1;
        $allOrders = [];
        $connectorTotal = 0;
        $filterApplied = null;
        $pagesFetched = 0;

        Log::info('order_map.sync.fetch', [
            'mode' => $mode,
            'status_ids' => $statusIds,
            'page_size' => $pageSize,
        ]);

        do {
            $response = $this->client->get($connection->order_api_endpoint, [
                'status_ids' => $statusIds,
                'page' => $page,
                'limit' => $pageSize,
            ]);

            $pagesFetched++;
            $rawOrders = is_array($response['orders'] ?? null) ? $response['orders'] : [];
            $allOrders = array_merge($allOrders, $rawOrders);

            if ($filterApplied === null && isset($response['filter_applied'])) {
                $filterApplied = (string) $response['filter_applied'];
            }

            if (isset($response['total'])) {
                $connectorTotal = max($connectorTotal, (int) $response['total']);
            }

            $hasNext = (bool) ($response['has_next'] ?? false);
            if (! $hasNext && $connectorTotal === 0) {
                $connectorTotal = count($allOrders);
            }

            Log::info('order_map.sync.fetch_page', [
                'mode' => $mode,
                'page' => $page,
                'page_count' => count($rawOrders),
                'has_next' => $hasNext,
                'filter_applied' => $response['filter_applied'] ?? null,
                'connector_total' => $response['total'] ?? null,
            ]);

            if ($rawOrders === []) {
                break;
            }

            $page++;
        } while ($hasNext && $page <= $maxPages);

        $connectorOrders = array_values(array_map(function ($order) {
            if (! is_array($order)) {
                return [
                    'order_id' => '',
                    'order_status_id' => 0,
                    'order_status_name' => '',
                ];
            }

            return [
                'order_id' => (string) ($order['source_order_id'] ?? $order['order_id'] ?? ''),
                'order_status_id' => (int) ($order['current_oc_status_id'] ?? $order['order_status_id'] ?? 0),
                'order_status_name' => (string) ($order['current_oc_status'] ?? $order['order_status_name'] ?? ''),
            ];
        }, $allOrders));

        Log::info('order_map.sync.connector_response', [
            'mode' => $mode,
            'requested_status_ids' => $statusIds,
            'raw_orders_count' => count($allOrders),
            'connector_total' => $connectorTotal > 0 ? $connectorTotal : count($allOrders),
            'pages_fetched' => $pagesFetched,
            'filter_applied' => $filterApplied,
            'orders' => $connectorOrders,
        ]);

        return [
            'orders' => $allOrders,
            'requested_status_ids' => $statusIds,
            'raw_orders_count' => count($allOrders),
            'connector_total' => $connectorTotal > 0 ? $connectorTotal : count($allOrders),
            'pages_fetched' => $pagesFetched,
            'filter_applied' => $filterApplied,
            'connector_orders' => $connectorOrders,
        ];
    }

    protected function resolveUser(?User $user): User
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            throw new RuntimeException('An authenticated user is required to sync orders.');
        }

        return $user;
    }

    protected function canApplyStatusUpdate(Order $order, SfmOrderStatus $mappedStatus): bool
    {
        $current = $order->sfm_status ?? SfmOrderStatus::New;

        if ($current === $mappedStatus) {
            return true;
        }

        if ($mappedStatus->isExternalSyncOnly()) {
            return true;
        }

        return $current->allowsSourceUpdate();
    }

    protected function applyStatusUpdateStockEffects(
        Order $order,
        SfmOrderStatus $previousStatus,
        SfmOrderStatus $newStatus,
        User $user
    ): void {
        if ($newStatus === SfmOrderStatus::Rejected && $previousStatus !== SfmOrderStatus::Rejected) {
            try {
                $this->stockService->restoreForOrder($order, $user);
            } catch (\Throwable $exception) {
                Log::warning('order_map.update.stock_restore_failed', [
                    'order_id' => $order->source_order_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($newStatus === SfmOrderStatus::ReturnReceived && $previousStatus !== SfmOrderStatus::ReturnReceived) {
            try {
                $this->stockService->restoreForReturnReceived($order, $user);
            } catch (\Throwable $exception) {
                Log::warning('order_map.update.return_received_stock_failed', [
                    'order_id' => $order->source_order_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  list<array{order_id: string, reason: string, detail: string}>  $skipLog
     */
    protected function recordSkip(
        array &$skipLog,
        string $orderId,
        string $reason,
        string $detail,
        string $mode,
        int $ocStatusId = 0,
        string $ocStatusName = '',
    ): void {
        $entry = [
            'order_id' => $orderId,
            'oc_status_id' => $ocStatusId,
            'oc_status_name' => $ocStatusName,
            'reason' => $reason,
            'detail' => $detail,
            'mode' => $mode,
        ];

        $skipLog[] = $entry;
        Log::info('order_map.sync.skipped', $entry);
    }

    /**
     * @param  array<string, mixed>  $orderData
     * @return array<string, mixed>
     */
    public function normalizeOrderPayload(array $orderData): array
    {
        $sourceOrderId = (string) ($orderData['source_order_id'] ?? $orderData['order_id'] ?? '');
        $statusId = (int) ($orderData['current_oc_status_id'] ?? $orderData['order_status_id'] ?? 0);
        $statusName = (string) ($orderData['current_oc_status'] ?? $orderData['order_status_name'] ?? '');
        $saleAmount = (float) ($orderData['sale_amount'] ?? $orderData['order_total'] ?? $orderData['cod_amount'] ?? 0);

        $items = [];
        foreach ($orderData['items'] ?? $orderData['products'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'source_product_id' => (string) ($item['source_product_id'] ?? $item['product_id'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? $item['name'] ?? ''),
                'model' => (string) ($item['model'] ?? $item['option_model'] ?? ''),
                'option_name' => (string) ($item['option_name'] ?? ''),
                'option_value' => (string) ($item['option_value'] ?? ''),
                'variant_label' => $item['variant_label'] ?? $this->buildVariantLabel($item),
                'quantity' => (int) ($item['quantity'] ?? $item['qty'] ?? 0),
                'sale_price' => (float) ($item['sale_price'] ?? $item['price'] ?? 0),
            ];
        }

        return [
            'source_order_id' => $sourceOrderId,
            'customer_name' => (string) ($orderData['customer_name'] ?? ''),
            'customer_phone' => (string) ($orderData['customer_phone'] ?? $orderData['phone'] ?? ''),
            'customer_address' => (string) ($orderData['customer_address'] ?? ''),
            'sale_amount' => $saleAmount,
            'current_oc_status_id' => $statusId,
            'current_oc_status' => $statusName,
            'courier_status' => $orderData['courier_status'] ?? null,
            'consignment_id' => $orderData['consignment_id'] ?? null,
            'created_at' => $orderData['created_at'] ?? null,
            'items' => $items,
            'source_snapshot' => $orderData,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function buildVariantLabel(array $item): ?string
    {
        $name = trim((string) ($item['option_name'] ?? ''));
        $value = trim((string) ($item['option_value'] ?? ''));

        if ($name !== '' && $value !== '') {
            return $name.': '.$value;
        }

        if ($value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $orderData
     */
    protected function importOrder(Supplier $supplier, array $orderData, SfmOrderStatus $mappedStatus, User $user): Order
    {
        $order = DB::transaction(function () use ($supplier, $orderData, $mappedStatus) {
            $order = Order::query()->create([
                'supplier_id' => $supplier->id,
                'source_order_id' => $orderData['source_order_id'],
                'customer_name' => $orderData['customer_name'],
                'customer_phone' => $orderData['customer_phone'],
                'customer_address' => $orderData['customer_address'],
                'sale_amount' => $orderData['sale_amount'],
                'current_oc_status' => $orderData['current_oc_status'],
                'current_oc_status_id' => $orderData['current_oc_status_id'] ?? null,
                'sfm_status' => $mappedStatus,
                'courier_status' => $orderData['courier_status'],
                'consignment_id' => $orderData['consignment_id'],
                'oc_created_at' => isset($orderData['created_at'])
                    ? Carbon::parse($orderData['created_at'])
                    : null,
                'source_snapshot' => $orderData['source_snapshot'],
                'stock_deducted' => false,
            ]);

            $this->syncOrderItems($order, $supplier, $orderData['items'] ?? []);

            return $order;
        });

        if ($mappedStatus === SfmOrderStatus::New) {
            $this->applyStockDeductionSafely($order, $user);
        }

        return $order->fresh(['items']);
    }

    protected function applyStockDeductionSafely(Order $order, User $user): void
    {
        try {
            $this->stockService->deductForOrder($order->loadMissing('items'), $user);
        } catch (\Throwable $exception) {
            Log::warning('order_map.load.stock_deduction_partial', [
                'order_id' => $order->source_order_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $orderData
     */
    protected function syncExistingOrder(
        Order $order,
        array $orderData,
        SfmOrderStatus $mappedStatus,
        bool $syncIbsStatus
    ): void {
        DB::transaction(function () use ($order, $orderData, $mappedStatus, $syncIbsStatus) {
            $updates = [
                'customer_name' => $orderData['customer_name'] ?: $order->customer_name,
                'customer_phone' => $orderData['customer_phone'] ?: $order->customer_phone,
                'customer_address' => $orderData['customer_address'] ?: $order->customer_address,
                'sale_amount' => $orderData['sale_amount'] ?: $order->sale_amount,
                'current_oc_status' => $orderData['current_oc_status'] ?: $order->current_oc_status,
                'current_oc_status_id' => $orderData['current_oc_status_id'] ?: $order->current_oc_status_id,
                'courier_status' => $orderData['courier_status'] ?? $order->courier_status,
                'consignment_id' => $orderData['consignment_id'] ?? $order->consignment_id,
                'source_snapshot' => $orderData['source_snapshot'],
            ];

            if ($syncIbsStatus) {
                $updates['sfm_status'] = $mappedStatus;
            }

            $order->update($updates);

            $order->items()->delete();
            $this->syncOrderItems($order, $order->supplier, $orderData['items'] ?? []);
        });
    }

    /**
     * @param  array<string, mixed>  $orderData
     * @deprecated Use syncExistingOrder()
     */
    protected function updateExistingOrder(Order $order, array $orderData): void
    {
        $this->syncExistingOrder($order, $orderData, $order->sfm_status ?? SfmOrderStatus::New, false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function syncOrderItems(Order $order, Supplier $supplier, array $items): void
    {
        foreach ($items as $itemData) {
            $match = $this->productMatcher->match($supplier, $itemData);
            $matched = $match['matched'];
            $cost = $matched ? $match['supplier_cost'] : 0;
            $now = now();

            OrderItem::query()->create([
                'order_id' => $order->id,
                'supplier_product_id' => null,
                'source_product_id' => (string) ($itemData['source_product_id'] ?? ''),
                'product_name' => (string) ($itemData['product_name'] ?? ''),
                'model' => (string) ($itemData['model'] ?? ''),
                'variant_label' => $itemData['variant_label'] ?? null,
                'option_name' => $itemData['option_name'] ?? null,
                'option_value' => $itemData['option_value'] ?? null,
                'is_unmatched' => ! $matched,
                'source_variant_key' => $match['variant_key'],
                'quantity' => (int) ($itemData['quantity'] ?? 0),
                'sale_price' => (float) ($itemData['sale_price'] ?? 0),
                'supplier_product_cost_snapshot' => $cost,
                'cost_snapshotted_at' => $matched ? $now : null,
                'item_status' => $matched ? OrderItemStatus::Active : OrderItemStatus::Unmatched,
            ]);
        }
    }

    protected function resolveSupplier(Connection $connection): Supplier
    {
        $supplier = Supplier::query()
            ->where('is_active', true)
            ->where(function ($query) use ($connection) {
                $query->where('code', $connection->supplier_filter)
                    ->orWhere('code', strtoupper($connection->supplier_filter));
            })
            ->first();

        if ($supplier) {
            return $supplier;
        }

        $fallback = Supplier::query()->where('is_active', true)->first();

        if (! $fallback) {
            throw new RuntimeException('No active supplier configured for order sync.');
        }

        return $fallback;
    }
}
