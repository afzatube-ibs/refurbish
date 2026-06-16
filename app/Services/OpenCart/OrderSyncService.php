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

        $orders = $this->fetchOrdersForRole(OrderSyncRole::ImportTrigger);

        $fetched = count($orders);
        $imported = 0;
        $duplicatesSkipped = 0;
        $unmatchedLines = 0;
        $skipLog = [];

        foreach ($orders as $orderData) {
            $normalized = $this->normalizeOrderPayload($orderData);
            $sourceOrderId = $normalized['source_order_id'];

            if ($sourceOrderId === '') {
                $this->recordSkip($skipLog, '—', 'missing_order_id', 'Order payload missing source order id.', 'import');

                continue;
            }

            $mapping = $this->orderStatusService->findMapping($normalized['current_oc_status_id']);

            if (! $mapping || $mapping->effectiveSyncRole() !== OrderSyncRole::ImportTrigger) {
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'not_import_trigger',
                    sprintf('OC status %d is not configured as Import Trigger.', $normalized['current_oc_status_id']),
                    'import'
                );

                continue;
            }

            if ($mapping->sfm_status !== SfmOrderStatus::New) {
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'invalid_import_mapping',
                    'Import Trigger requires IBS Status New.',
                    'import'
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
                    'import'
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
            'unmatched_lines' => $unmatchedLines,
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

        $orders = $this->fetchOrdersForRole(OrderSyncRole::UpdateExisting);

        $fetched = count($orders);
        $updated = 0;
        $notFoundSkipped = 0;
        $lockedSkipped = 0;
        $skipLog = [];

        foreach ($orders as $orderData) {
            $normalized = $this->normalizeOrderPayload($orderData);
            $sourceOrderId = $normalized['source_order_id'];

            if ($sourceOrderId === '') {
                $this->recordSkip($skipLog, '—', 'missing_order_id', 'Order payload missing source order id.', 'update');

                continue;
            }

            $mapping = $this->orderStatusService->findMapping($normalized['current_oc_status_id']);

            if (! $mapping || $mapping->effectiveSyncRole() !== OrderSyncRole::UpdateExisting) {
                $this->recordSkip(
                    $skipLog,
                    $sourceOrderId,
                    'not_update_existing',
                    sprintf('OC status %d is not configured as Update Existing Only.', $normalized['current_oc_status_id']),
                    'update'
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
                    'update'
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
                    'update'
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
                    'update'
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
            'skip_log' => $skipLog,
        ];

        $this->loadLogService->record($summary);
        Log::info('order_map.update.complete', $summary);

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchOrdersForRole(OrderSyncRole $role): array
    {
        $connection = Connection::getInstance();

        $statusIds = OrderStatusMapping::query()
            ->where('oc_selected', true)
            ->where('sync_role', $role)
            ->pluck('source_status_id')
            ->all();

        $response = $this->client->get($connection->order_api_endpoint, [
            'status_ids' => $statusIds,
        ]);

        return is_array($response['orders'] ?? null) ? $response['orders'] : [];
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
            Log::warning('order_map.update.return_received_stock', [
                'order_id' => $order->source_order_id,
                'message' => 'Return received stock restore not yet automated.',
            ]);
        }
    }

    /**
     * @param  list<array{order_id: string, reason: string, detail: string}>  $skipLog
     */
    protected function recordSkip(array &$skipLog, string $orderId, string $reason, string $detail, string $mode): void
    {
        $entry = [
            'order_id' => $orderId,
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
