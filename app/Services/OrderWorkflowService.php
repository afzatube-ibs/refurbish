<?php

namespace App\Services;

use App\Enums\SfmOrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OrderWorkflowService
{
    public function __construct(
        protected OrderStatusEngine $statusEngine,
        protected DispatchService $dispatchService,
        protected ActivityLogService $activityLog
    ) {}

    public function accept(Order $order): Order
    {
        return $this->transition($order, SfmOrderStatus::Accepted, 'order.accepted');
    }

    public function pack(Order $order): Order
    {
        return $this->transition($order, SfmOrderStatus::Packed, 'order.packed');
    }

    public function dispatch(
        Order $order,
        string $courier,
        string $consignmentId,
        ?\DateTimeInterface $dispatchDate = null
    ): Order {
        if ($order->sfm_status !== SfmOrderStatus::Packed) {
            throw new InvalidArgumentException('Order must be packed before dispatch.');
        }

        if (! $this->statusEngine->canTransition(SfmOrderStatus::Packed, SfmOrderStatus::Dispatched)) {
            throw new RuntimeException('Dispatch transition is not allowed.');
        }

        return DB::transaction(function () use ($order, $courier, $consignmentId, $dispatchDate) {
            $this->dispatchService->create($order, $courier, $consignmentId, $dispatchDate);

            $order->refresh();

            $this->activityLog->log('order.dispatched', Order::class, $order->id, [
                'courier' => $courier,
                'consignment_id' => $consignmentId,
            ]);

            return $order;
        });
    }

    public function cancel(Order $order): Order
    {
        return $this->transition($order, SfmOrderStatus::Cancelled, 'order.cancelled');
    }

    protected function transition(Order $order, SfmOrderStatus $to, string $action): Order
    {
        $from = $order->sfm_status ?? SfmOrderStatus::New;

        if (! $this->statusEngine->canTransition($from, $to)) {
            throw new InvalidArgumentException(
                sprintf('Cannot transition order from %s to %s.', $from->value, $to->value)
            );
        }

        $order->update(['sfm_status' => $to]);

        $this->activityLog->log($action, Order::class, $order->id, [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        return $order->fresh();
    }
}
