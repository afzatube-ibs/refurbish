<?php

namespace App\Services\OpenCart;

use App\Enums\OrderItemStatus;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusMapping;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderMap\OrderMapProductMatcher;
use App\Services\OrderMap\OrderMapStockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderSyncService
{
    public function __construct(
        protected OpenCartHttpClient $client,
        protected OrderStatusService $orderStatusService,
        protected OrderMapProductMatcher $productMatcher,
        protected OrderMapStockService $stockService,
    ) {}

    /**
     * Manual order load — status-filtered only, no warehouse/product-map dependency.
     *
     * @return array{imported: int, skipped: int, updated: int}
     */
    public function load(?User $user = null): array
    {
        return $this->sync($user);
    }

    /**
     * @return array{imported: int, skipped: int, updated: int}
     */
    public function sync(?User $user = null): array
    {
        app(ConnectionService::class)->assertSyncAllowed();
        $connection = Connection::getInstance();
        $supplier = $this->resolveSupplier($connection);
        $user ??= auth()->user();

        if (! $user instanceof User) {
            throw new RuntimeException('An authenticated user is required to load orders.');
        }

        $statusIds = OrderStatusMapping::query()
            ->where('sfm_status', '!=', SfmOrderStatus::Ignore)
            ->pluck('source_status_id')
            ->all();

        $params = [
            'status_ids' => $statusIds,
        ];

        $response = $this->client->get($connection->order_api_endpoint, $params);

        $imported = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($response['orders'] ?? [] as $orderData) {
            $normalized = $this->normalizeOrderPayload($orderData);
            $sourceOrderId = $normalized['source_order_id'];

            if ($sourceOrderId === '') {
                $skipped++;

                continue;
            }

            $mappedStatus = $this->orderStatusService->applyMapping($normalized['current_oc_status_id']);

            if ($mappedStatus === SfmOrderStatus::Ignore) {
                $skipped++;

                continue;
            }

            $existing = Order::query()
                ->where('source_order_id', $sourceOrderId)
                ->first();

            if ($existing) {
                if ($mappedStatus->isExternalSyncOnly()) {
                    $this->syncExistingOrder($existing, $normalized, $mappedStatus, true);
                    $updated++;
                } elseif ($mappedStatus === SfmOrderStatus::New) {
                    if ($existing->sfm_status?->allowsSourceUpdate()) {
                        $this->syncExistingOrder($existing, $normalized, $mappedStatus, false);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } elseif ($existing->sfm_status?->allowsSourceUpdate()) {
                    $this->syncExistingOrder($existing, $normalized, $mappedStatus, false);
                    $updated++;
                } else {
                    $skipped++;
                }

                continue;
            }

            if (! $mappedStatus->allowsImportCreate()) {
                $skipped++;

                continue;
            }

            $this->importOrder($supplier, $normalized, $mappedStatus, $user);
            $imported++;
        }

        return compact('imported', 'skipped', 'updated');
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
        return DB::transaction(function () use ($supplier, $orderData, $mappedStatus, $user) {
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

            if ($mappedStatus === SfmOrderStatus::New) {
                $this->stockService->deductForOrder($order->fresh(['items']), $user);
            }

            return $order;
        });
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
            $cost = $match['supplier_cost'];
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
                'is_unmatched' => ! $match['matched'],
                'source_variant_key' => $match['variant_key'],
                'quantity' => (int) ($itemData['quantity'] ?? 0),
                'sale_price' => (float) ($itemData['sale_price'] ?? 0),
                'supplier_product_cost_snapshot' => $cost,
                'cost_snapshotted_at' => $cost !== null ? $now : null,
                'item_status' => $match['matched'] ? OrderItemStatus::Active : OrderItemStatus::Unmatched,
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
