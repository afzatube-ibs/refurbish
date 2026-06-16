<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusEngine;

class OrderPolicy
{
    public function __construct(
        private readonly OrderStatusEngine $statusEngine,
    ) {}
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupplier();
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->supplier_id === $order->supplier_id;
    }

    public function update(User $user, Order $order): bool
    {
        if (! $this->view($user, $order)) {
            return false;
        }

        return $this->statusEngine->canEditOrder($order);
    }
}
