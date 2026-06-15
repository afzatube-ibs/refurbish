<?php

namespace App\Services;

use App\Enums\SfmOrderStatus;
use App\Models\Order;

class OrderStatusEngine
{
    public function mergeOcStatus(Order $order, SfmOrderStatus $mappedStatus): SfmOrderStatus
    {
        $current = $order->sfm_status ?? SfmOrderStatus::New;

        if ($mappedStatus === SfmOrderStatus::Ignore) {
            return $current;
        }

        if ($this->isOverrideStatus($mappedStatus)) {
            return $mappedStatus;
        }

        if ($mappedStatus === SfmOrderStatus::Returned
            && $this->isAtLeast($current, SfmOrderStatus::Dispatched)
            && $current !== SfmOrderStatus::Cancelled) {
            return SfmOrderStatus::Returned;
        }

        if ($mappedStatus === SfmOrderStatus::Delivered
            && $this->isAtLeast($current, SfmOrderStatus::Dispatched)
            && $current !== SfmOrderStatus::Cancelled
            && $current !== SfmOrderStatus::Returned) {
            return SfmOrderStatus::Delivered;
        }

        if ($this->rank($current) < $this->rank(SfmOrderStatus::Dispatched)) {
            return $current;
        }

        if ($this->rank($mappedStatus) <= $this->rank($current)) {
            return $current;
        }

        return $mappedStatus;
    }

    public function canTransition(SfmOrderStatus $from, SfmOrderStatus $to): bool
    {
        $allowed = config('dropflow.supplier_transitions.'.$from->value, []);

        return in_array($to->value, $allowed, true);
    }

    public function rank(SfmOrderStatus $status): int
    {
        return (int) config('dropflow.status_ranks.'.$status->value, 0);
    }

    public function isAtLeast(SfmOrderStatus $status, SfmOrderStatus $minimum): bool
    {
        return $this->rank($status) >= $this->rank($minimum);
    }

    public function isOverrideStatus(SfmOrderStatus $status): bool
    {
        return in_array($status->value, config('dropflow.oc_override_statuses', []), true);
    }

    /**
     * @return list<string>
     */
    public function availableSupplierTransitions(SfmOrderStatus $current): array
    {
        return config('dropflow.supplier_transitions.'.$current->value, []);
    }
}
