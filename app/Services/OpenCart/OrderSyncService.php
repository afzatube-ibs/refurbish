<?php

namespace App\Services\OpenCart;

use App\Enums\OrderItemStatus;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusMapping;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\OrderStatusEngine;
use App\Services\ReturnService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderSyncService
{
    public function __construct(
        protected OpenCartHttpClient $client,
        protected OrderStatusService $orderStatusService,
        protected OrderStatusEngine $statusEngine,
        protected ReturnService $returnService
    ) {}

    /**
     * @return array{imported: int, skipped: int, updated: int}
     */
    public function sync(): array
    {
        app(ConnectionService::class)->assertSyncAllowed();
        $connection = Connection::getInstance();
        $supplier = $this->resolveSupplier($connection);

        $statusIds = OrderStatusMapping::query()
            ->where('sfm_status', '!=', SfmOrderStatus::Ignore)
            ->pluck('source_status_id')
            ->all();

        $params = [
            'status_ids' => $statusIds,
        ];

        if ($connection->last_order_sync_at) {
            $params['since'] = $connection->last_order_sync_at->toIso8601String();
        }

        $response = $this->client->get($connection->order_api_endpoint, $params);

        $imported = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($response['orders'] ?? [] as $orderData) {
            $sourceOrderId = (string) ($orderData['source_order_id'] ?? '');

            if ($sourceOrderId === '') {
                $skipped++;

                continue;
            }

            $mappedStatus = $this->orderStatusService->applyMapping(
                (int) ($orderData['current_oc_status_id'] ?? 0)
            );

            if ($mappedStatus === SfmOrderStatus::Ignore) {
                $skipped++;

                continue;
            }

            $existing = Order::query()
                ->where('source_order_id', $sourceOrderId)
                ->first();

            if ($existing) {
                $this->updateExistingOrder($existing, $orderData, $mappedStatus);
                $updated++;
            } else {
                $this->importOrder($supplier, $orderData, $mappedStatus);
                $imported++;
            }
        }

        $connection->last_order_sync_at = now();
        $connection->save();

        return compact('imported', 'skipped', 'updated');
    }

    protected function importOrder(Supplier $supplier, array $orderData, SfmOrderStatus $mappedStatus): Order
    {
        return DB::transaction(function () use ($supplier, $orderData, $mappedStatus) {
            $order = Order::query()->create([
                'supplier_id' => $supplier->id,
                'source_order_id' => (string) $orderData['source_order_id'],
                'customer_name' => (string) ($orderData['customer_name'] ?? ''),
                'customer_phone' => (string) ($orderData['customer_phone'] ?? ''),
                'customer_address' => (string) ($orderData['customer_address'] ?? ''),
                'sale_amount' => (float) ($orderData['sale_amount'] ?? 0),
                'current_oc_status' => (string) ($orderData['current_oc_status'] ?? ''),
                'sfm_status' => $mappedStatus,
                'courier_status' => $orderData['courier_status'] ?? null,
                'consignment_id' => $orderData['consignment_id'] ?? null,
                'oc_created_at' => isset($orderData['created_at'])
                    ? Carbon::parse($orderData['created_at'])
                    : null,
            ]);

            $this->syncOrderItems($order, $orderData['items'] ?? []);

            if ($mappedStatus === SfmOrderStatus::Returned) {
                $this->returnService->markPendingFromOc($order);
            }

            return $order;
        });
    }

    protected function updateExistingOrder(Order $order, array $orderData, SfmOrderStatus $mappedStatus): void
    {
        DB::transaction(function () use ($order, $orderData, $mappedStatus) {
            $newStatus = $this->statusEngine->mergeOcStatus($order, $mappedStatus);

            $order->update([
                'customer_name' => (string) ($orderData['customer_name'] ?? $order->customer_name),
                'customer_phone' => (string) ($orderData['customer_phone'] ?? $order->customer_phone),
                'customer_address' => (string) ($orderData['customer_address'] ?? $order->customer_address),
                'sale_amount' => (float) ($orderData['sale_amount'] ?? $order->sale_amount),
                'current_oc_status' => (string) ($orderData['current_oc_status'] ?? $order->current_oc_status),
                'courier_status' => $orderData['courier_status'] ?? $order->courier_status,
                'consignment_id' => $orderData['consignment_id'] ?? $order->consignment_id,
                'sfm_status' => $newStatus,
            ]);

            if ($newStatus === SfmOrderStatus::Returned) {
                $this->returnService->markPendingFromOc($order->fresh());
            }
        });
    }

    protected function syncOrderItems(Order $order, array $items): void
    {
        foreach ($items as $itemData) {
            $sourceProductId = (string) ($itemData['source_product_id'] ?? '');
            $variantLabel = $itemData['variant_label'] ?? null;

            $supplierProduct = SupplierProduct::query()
                ->where('supplier_id', $order->supplier_id)
                ->where('source_product_id', $sourceProductId)
                ->first();

            OrderItem::query()->create([
                'order_id' => $order->id,
                'supplier_product_id' => $supplierProduct?->id,
                'source_product_id' => $sourceProductId,
                'product_name' => (string) ($itemData['product_name'] ?? ''),
                'model' => (string) ($itemData['model'] ?? ''),
                'variant_label' => $variantLabel,
                'quantity' => (int) ($itemData['quantity'] ?? 0),
                'sale_price' => (float) ($itemData['sale_price'] ?? 0),
                'item_status' => OrderItemStatus::Active,
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
