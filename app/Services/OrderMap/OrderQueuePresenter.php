<?php

namespace App\Services\OrderMap;

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

        return [
            'order' => $order,
            'total_qty' => $totalQty,
            'total_cost' => $totalCost,
            'has_unmatched' => $hasUnmatched,
            'product_cards' => $this->productCards($order->items),
        ];
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
