<?php

namespace App\Services\OrderMap;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\ProductControlVariant;
use App\Models\ProductMap\StockAdjustmentHistory;
use App\Models\User;
use App\Services\ProductMap\ProductControlPersistenceService;
use Illuminate\Support\Facades\Log;

class OrderMapStockService
{
    public function deductForOrder(Order $order, User $user): void
    {
        if ($order->stock_deducted) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->is_unmatched || $item->item_status === \App\Enums\OrderItemStatus::Unmatched) {
                continue;
            }

            $this->adjustItemStock($order, $item, -1 * (int) $item->quantity, $user, 'Order import #'.$order->source_order_id);
        }

        $order->update(['stock_deducted' => true]);
    }

    public function restoreForOrder(Order $order, User $user): void
    {
        if (! $order->stock_deducted) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->is_unmatched || $item->item_status === \App\Enums\OrderItemStatus::Unmatched) {
                continue;
            }

            $this->adjustItemStock($order, $item, (int) $item->quantity, $user, 'Order rejected #'.$order->source_order_id);
        }

        $order->update(['stock_deducted' => false]);
    }

    protected function adjustItemStock(Order $order, OrderItem $item, int $delta, User $user, string $note): void
    {
        if ($delta === 0) {
            return;
        }

        $sourceProductId = (string) $item->source_product_id;
        $variantKey = (string) ($item->source_variant_key ?: ProductControlPersistenceService::SIMPLE_STOCK_KEY);

        $state = ProductControlState::query()
            ->where('supplier_id', $order->supplier_id)
            ->where('source_product_id', $sourceProductId)
            ->first();

        if (! $state) {
            return;
        }

        $variant = ProductControlVariant::query()
            ->where('product_control_state_id', $state->id)
            ->where('source_variant_key', $variantKey)
            ->first();

        if (! $variant && $variantKey !== ProductControlPersistenceService::SIMPLE_STOCK_KEY) {
            $variant = ProductControlVariant::query()
                ->where('product_control_state_id', $state->id)
                ->where('source_variant_key', ProductControlPersistenceService::SIMPLE_STOCK_KEY)
                ->first();
        }

        if (! $variant) {
            $variant = ProductControlVariant::query()->firstOrCreate(
                [
                    'product_control_state_id' => $state->id,
                    'source_variant_key' => $variantKey,
                ],
                [
                    'ibs_model' => null,
                    'sm_model' => null,
                    'rate' => null,
                    'ibs_stock' => null,
                    'low_warning' => null,
                ]
            );
        }

        $oldStock = $variant->ibs_stock;
        $baseStock = $oldStock ?? 0;
        $newStock = $baseStock + $delta;

        if ($newStock < 0) {
            Log::warning('order_map.stock.insufficient', [
                'product_id' => $sourceProductId,
                'variant_key' => $variantKey,
                'order_id' => $order->source_order_id,
                'requested_delta' => $delta,
                'available' => $baseStock,
            ]);

            return;
        }

        $variant->ibs_stock = $newStock;
        $variant->save();

        StockAdjustmentHistory::query()->create([
            'supplier_id' => $order->supplier_id,
            'product_id' => $sourceProductId,
            'variant_id' => $variantKey === ProductControlPersistenceService::SIMPLE_STOCK_KEY ? null : $variantKey,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'difference' => $delta,
            'reason' => (string) config('dropflow.order_stock_reason', 'Correction'),
            'note' => $note,
            'changed_by' => $user->id,
        ]);
    }
}
