<?php

namespace App\Services\OrderMap;

use App\Enums\SfmOrderStatus;
use App\Models\DispatchBatchOrder;
use App\Models\Order;
use Illuminate\Support\Collection;

class OrderQueuePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $order->loadMissing('items');

        $totalQty = (int) $order->items->sum('quantity');
        $totalCost = (float) $order->items->sum(function ($item) {
            $unit = $item->supplier_product_cost_snapshot;

            return $unit !== null ? ((float) $unit * (int) $item->quantity) : 0;
        });
        $hasUnmatched = $order->items->contains(fn ($item) => $item->is_unmatched);
        $alreadyBatched = DispatchBatchOrder::query()->where('order_id', $order->id)->exists();
        $isBatchable = $order->sfm_status === SfmOrderStatus::Packed && ! $alreadyBatched;

        return [
            'order' => $order,
            'total_qty' => $totalQty,
            'total_cost' => $totalCost,
            'has_unmatched' => $hasUnmatched,
            'already_batched' => $alreadyBatched,
            'is_batchable' => $isBatchable,
            'source_label' => $this->sourceLabel($order),
            'oc_status_label' => $this->ocStatusLabel($order),
            'product_cards' => $this->productCards($order->items),
        ];
    }

    protected function sourceLabel(Order $order): string
    {
        if (is_array($order->source_snapshot)) {
            $label = trim((string) ($order->source_snapshot['source_label'] ?? ''));

            if ($label !== '') {
                return $label;
            }

            if (! empty($order->source_snapshot['manual'])) {
                return 'Lokkisona Manual';
            }
        }

        if (str_starts_with((string) $order->source_order_id, 'MAN-')) {
            return 'Lokkisona Manual';
        }

        return 'Lokkisona';
    }

    protected function ocStatusLabel(Order $order): string
    {
        $statusId = (int) ($order->current_oc_status_id ?? 0);
        $statusName = (string) ($order->current_oc_status ?? '');

        if ($statusId === 0 && is_array($order->source_snapshot)) {
            $statusId = (int) ($order->source_snapshot['current_oc_status_id'] ?? $order->source_snapshot['order_status_id'] ?? 0);
            $statusName = (string) ($order->source_snapshot['current_oc_status'] ?? $order->source_snapshot['order_status_name'] ?? $statusName);
        }

        if ($statusName !== '' && $statusId > 0) {
            return sprintf('%s (#%d)', $statusName, $statusId);
        }

        if ($statusId > 0) {
            return sprintf('#%d', $statusId);
        }

        return $statusName !== '' ? $statusName : '—';
    }

    /**
     * @param  Collection<int, \App\Models\OrderItem>  $items
     * @return list<array<string, mixed>>
     */
    protected function productCards(Collection $items): array
    {
        return $items->map(function ($item) {
            $option = trim((string) ($item->option_value ?? ''));
            if ($option === '' && $item->variant_label) {
                $option = (string) $item->variant_label;
            }

            return [
                'name' => $item->product_name,
                'model' => $item->model,
                'option' => $option !== '' ? $option : null,
                'qty' => (int) $item->quantity,
                'cost' => $item->supplier_product_cost_snapshot,
                'unmatched' => (bool) $item->is_unmatched,
            ];
        })->all();
    }
}
