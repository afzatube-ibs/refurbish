<?php

namespace App\Services\ProductMap;

use App\Models\ProductMap\ProductRateHistory;
use App\Models\Supplier;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ProductControlRateResolver
{
    public function __construct(
        private readonly ProductControlSupplierResolver $supplierResolver,
    ) {}

    public function rateAt(
        string $productId,
        ?string $variantId,
        CarbonInterface $asOf,
        ?Supplier $supplier = null,
    ): ?float {
        $supplier ??= $this->supplierResolver->resolve();

        $query = ProductRateHistory::query()
            ->where('supplier_id', $supplier->id)
            ->where('product_id', $productId)
            ->where('effective_from', '<=', Carbon::instance($asOf))
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($variantId === null || $variantId === '') {
            $query->whereNull('variant_id');
        } else {
            $query->where('variant_id', $variantId);
        }

        $row = $query->first();

        if ($row) {
            return (float) $row->new_rate;
        }

        if ($variantId !== null && $variantId !== '') {
            $parentRow = ProductRateHistory::query()
                ->where('supplier_id', $supplier->id)
                ->where('product_id', $productId)
                ->whereNull('variant_id')
                ->where('effective_from', '<=', Carbon::instance($asOf))
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($parentRow) {
                return (float) $parentRow->new_rate;
            }
        }

        return null;
    }
}
