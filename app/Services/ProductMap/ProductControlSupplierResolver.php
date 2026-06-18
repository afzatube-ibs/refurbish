<?php

namespace App\Services\ProductMap;

use App\Models\Connection;
use App\Models\Supplier;
use App\Services\OperationalDefaultsService;

class ProductControlSupplierResolver
{
    public function __construct(
        protected OperationalDefaultsService $defaults,
    ) {}

    public function resolve(?Connection $connection = null): Supplier
    {
        return $this->defaults->defaultSupplier($connection);
    }
}
