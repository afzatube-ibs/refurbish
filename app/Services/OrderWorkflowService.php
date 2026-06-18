<?php

namespace App\Services;

use App\Enums\SfmOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderMap\OrderMapStockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OrderWorkflowService
{
    public function __construct(
        protected OrderStatusEngine $statusEngine,
        protected DispatchService $dispatchService,
        protected ReturnService $returnService,
        protected OrderMapStockService $stockService,
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

        $courier = trim($courier);
        $consignmentId = trim($consignmentId);

        if ($consignmentId === '') {
            throw new InvalidArgumentException('Consignment ID is required for dispatch.');
        }

        return DB::transaction(function () use ($order, $courier, $consignmentId, $dispatchDate) {
            $this->dispatchService->create($order, $courier, $consignmentId, $dispatchDate);

            $order->update([
                'sfm_status' => SfmOrderStatus::Dispatched,
                'consignment_id' => $consignmentId,
                'courier_name' => $courier,
            ]);

            $order->refresh();

            $this->activityLog->log('order.dispatched', Order::class, $order->id, [
                'courier' => $courier,
                'consignment_id' => $consignmentId,
            ]);

            return $order;
        });
    }

    public function reject(Order $order, ?User $user = null): Order
    {
        $user ??= auth()->user();

        return DB::transaction(function () use ($order, $user) {
            $from = $order->sfm_status ?? SfmOrderStatus::New;

            if (! $this->statusEngine->canTransition($from, SfmOrderStatus::Rejected)) {
                throw new InvalidArgumentException(
                    sprintf('Cannot reject order from %s.', $from->label())
                );
            }

            if ($user instanceof User) {
                $this->stockService->restoreForOrder($order, $user);
            }

            $order->update(['sfm_status' => SfmOrderStatus::Rejected]);

            $this->activityLog->log('order.rejected', Order::class, $order->id, [
                'from' => $from->value,
            ]);

            return $order->fresh();
        });
    }

    public function moveToReturnQueue(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $this->returnService->markPendingFromOc($order);

            return $order->fresh();
        });
    }

    public function markReturnReceived(Order $order, ?User $user = null): Order
    {
        $user ??= auth()->user();

        return DB::transaction(function () use ($order, $user) {
            $from = $order->sfm_status ?? SfmOrderStatus::New;

            if (! $this->statusEngine->canTransition($from, SfmOrderStatus::ReturnReceived)) {
                throw new InvalidArgumentException(
                    sprintf('Cannot mark return received from %s.', $from->label())
                );
            }

            if ($user instanceof User) {
                $this->stockService->restoreForReturnReceived($order, $user);
            }

            if (! $user instanceof User) {
                throw new InvalidArgumentException('A user is required to confirm a return.');
            }

            $return = $order->returns()->first();

            if (! $return) {
                $return = $this->returnService->markPendingFromOc($order);
            }

            $this->returnService->confirmReceived($return, $user);

            $order->update(['sfm_status' => SfmOrderStatus::ReturnReceived]);

            $this->activityLog->log('order.return_received', Order::class, $order->id, [
                'from' => $from->value,
            ]);

            return $order->fresh();
        });
    }

    public function complete(Order $order): Order
    {
        return $this->transition($order, SfmOrderStatus::Completed, 'order.completed');
    }

    /** @deprecated Use reject() */
    public function cancel(Order $order): Order
    {
        return $this->reject($order);
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
