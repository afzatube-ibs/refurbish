<?php

namespace App\Policies;

use App\Models\SupplierProduct;
use App\Models\User;

class SupplierProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SupplierProduct $supplierProduct): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SupplierProduct $supplierProduct): bool
    {
        return $user->isAdmin();
    }
}
