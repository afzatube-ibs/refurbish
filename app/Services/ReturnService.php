<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\ReturnStatus;
use App\Enums\SfmOrderStatus;
use App\Models\Order;
use App\Models\ReturnItem;
use App\Models\ReturnModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReturnService
{
    public function __construct(
        protected ActivityLogService $activityLog
    ) {}

    public function markPendingFromOc(Order $order): ReturnModel
    {
        return DB::transaction(function () use ($order) {
            $order->loadMissing('items');

            $return = ReturnModel::query()->where('order_id', $order->id)->first();

            if ($return?->return_status === ReturnStatus::Confirmed) {
                return $return;
            }

            if (! $return) {
                $return = ReturnModel::query()->create([
                    'order_id' => $order->id,
                    'supplier_id' => $order->supplier_id,
                    'return_status' => ReturnStatus::Pending,
                ]);
            }

            foreach ($order->items as $item) {
                if ($item->item_status === OrderItemStatus::Cancelled) {
                    continue;
                }

                $cost = $item->supplier_product_cost_snapshot ?? 0;

                ReturnItem::query()->updateOrCreate(
                    [
                        'return_id' => $return->id,
                        'order_item_id' => $item->id,
                    ],
                    [
                        'quantity' => $item->quantity,
                        'supplier_cost_snapshot' => $cost,
                    ]
                );

                if ($item->item_status === OrderItemStatus::Active) {
                    $item->update(['item_status' => OrderItemStatus::ReturnPending]);
                }
            }

            if ($order->sfm_status !== SfmOrderStatus::Returned) {
                $order->update(['sfm_status' => SfmOrderStatus::Returned]);
            }

            $this->activityLog->log('return.pending', ReturnModel::class, $return->id, [
                'order_id' => $order->id,
                'source' => 'opencart',
            ]);

            return $return->fresh(['returnItems']);
        });
    }

    public function confirmReceived(
        ReturnModel $return,
        User $user,
        ?\DateTimeInterface $receivedDate = null
    ): ReturnModel {
        if ($return->return_status === ReturnStatus::Confirmed) {
            throw new InvalidArgumentException('Return has already been confirmed.');
        }

        return DB::transaction(function () use ($return, $user, $receivedDate) {
            $receivedDate = $receivedDate ?? now();

            $return->update([
                'return_status' => ReturnStatus::Confirmed,
                'received_date' => $receivedDate,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
            ]);

            $return->loadMissing('returnItems.orderItem');

            foreach ($return->returnItems as $returnItem) {
                $returnItem->orderItem?->update([
                    'item_status' => OrderItemStatus::Returned,
                ]);
            }

            $this->activityLog->log('return.confirmed', ReturnModel::class, $return->id, [
                'confirmed_by' => $user->id,
                'received_date' => $receivedDate instanceof \DateTimeInterface
                    ? $receivedDate->format('Y-m-d')
                    : (string) $receivedDate,
            ]);

            return $return->fresh(['returnItems.orderItem']);
        });
    }
}
