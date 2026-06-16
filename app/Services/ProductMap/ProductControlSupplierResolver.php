<?php

namespace App\Services\ProductMap;

use App\Models\Connection;
use App\Models\Supplier;
use RuntimeException;

class ProductControlSupplierResolver
{
    public function resolve(?Connection $connection = null): Supplier
    {
        $connection ??= Connection::getInstance();

        $supplier = Supplier::query()
            ->where('is_active', true)
            ->where(function ($query) use ($connection) {
                $query->where('code', $connection->supplier_filter)
                    ->orWhere('code', strtoupper((string) $connection->supplier_filter));
            })
            ->first();

        if ($supplier) {
            return $supplier;
        }

        $fallback = Supplier::query()->where('is_active', true)->first();

        if (! $fallback) {
            throw new RuntimeException('No active supplier configured for product control.');
        }

        return $fallback;
    }
}
