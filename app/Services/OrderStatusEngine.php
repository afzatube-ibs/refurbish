<?php

namespace App\Services;

use App\Enums\SfmOrderStatus;
use App\Models\Order;

class OrderStatusEngine
{
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

    /**
     * @return list<string>
     */
    public function availableSupplierTransitions(SfmOrderStatus $current): array
    {
        return config('dropflow.supplier_transitions.'.$current->value, []);
    }

    public function canUpdateFromSource(Order $order): bool
    {
        $status = $order->sfm_status ?? SfmOrderStatus::New;

        return $status->allowsSourceUpdate();
    }
}
