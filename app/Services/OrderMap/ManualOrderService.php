<?php

namespace App\Services\OrderMap;

use App\Enums\OrderItemStatus;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ManualOrderService
{
    public function __construct(
        protected OrderMapProductMatcher $productMatcher,
        protected OrderMapStockService $stockService,
    ) {}

    /**
     * @param  array{
     *     customer_name: string,
     *     customer_phone: string,
     *     customer_address: string,
     *     items: list<array{
     *         source_product_id: string,
     *         product_name: string,
     *         model?: string|null,
     *         quantity: int,
     *         sale_price?: float|null
     *     }>
     * }  $data
     */
    public function create(array $data, User $user): Order
    {
        $supplier = $this->resolveSupplier(Connection::getInstance());

        $order = DB::transaction(function () use ($supplier, $data, $user) {
            $saleAmount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (int) $item['quantity'];
                $price = (float) ($item['sale_price'] ?? 0);
                $saleAmount += $qty * $price;
            }

            $order = Order::query()->create([
                'supplier_id' => $supplier->id,
                'source_order_id' => $this->nextManualOrderId(),
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_address' => $data['customer_address'],
                'sale_amount' => $saleAmount,
                'current_oc_status' => 'Manual',
                'current_oc_status_id' => null,
                'sfm_status' => SfmOrderStatus::New,
                'courier_status' => null,
                'consignment_id' => null,
                'oc_created_at' => now(),
                'source_snapshot' => [
                    'manual' => true,
                    'created_by' => $user->id,
                ],
                'stock_deducted' => false,
            ]);

            foreach ($data['items'] as $itemData) {
                $this->createOrderItem($order, $supplier, $itemData);
            }

            return $order;
        });

        $this->applyStockDeductionSafely($order->fresh(['items']), $user);

        return $order->fresh(['items']);
    }

    protected function nextManualOrderId(): string
    {
        $latest = Order::query()
            ->where('source_order_id', 'like', 'MAN-%')
            ->orderByDesc('id')
            ->value('source_order_id');

        $sequence = 1;

        if (is_string($latest) && preg_match('/^MAN-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return 'MAN-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    protected function createOrderItem(Order $order, Supplier $supplier, array $itemData): void
    {
        $matchPayload = [
            'source_product_id' => (string) $itemData['source_product_id'],
            'product_name' => (string) $itemData['product_name'],
            'model' => (string) ($itemData['model'] ?? ''),
            'quantity' => (int) $itemData['quantity'],
            'sale_price' => (float) ($itemData['sale_price'] ?? 0),
        ];

        $match = $this->productMatcher->match($supplier, $matchPayload);
        $matched = $match['matched'];
        $cost = $matched ? $match['supplier_cost'] : 0;
        $now = now();

        OrderItem::query()->create([
            'order_id' => $order->id,
            'supplier_product_id' => null,
            'source_product_id' => $matchPayload['source_product_id'],
            'product_name' => $matchPayload['product_name'],
            'model' => $matchPayload['model'],
            'variant_label' => $matchPayload['model'] !== '' ? $matchPayload['model'] : null,
            'option_name' => null,
            'option_value' => null,
            'is_unmatched' => ! $matched,
            'source_variant_key' => $match['variant_key'],
            'quantity' => $matchPayload['quantity'],
            'sale_price' => $matchPayload['sale_price'],
            'supplier_product_cost_snapshot' => $cost,
            'cost_snapshotted_at' => $matched ? $now : null,
            'item_status' => $matched ? OrderItemStatus::Active : OrderItemStatus::Unmatched,
        ]);
    }

    protected function applyStockDeductionSafely(Order $order, User $user): void
    {
        try {
            $this->stockService->deductForOrder($order, $user);
        } catch (\Throwable $exception) {
            Log::warning('order_map.manual.stock_deduction_partial', [
                'order_id' => $order->source_order_id,
                'message' => $exception->getMessage(),
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
            throw new RuntimeException('No active supplier configured for manual orders.');
        }

        return $fallback;
    }
}
