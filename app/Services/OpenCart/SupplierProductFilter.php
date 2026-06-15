<?php

namespace App\Services\OpenCart;

class SupplierProductFilter
{
    /**
     * IBS warehouse products are pre-filtered via dispatch_location_product.
     * DropFlow only confirms from_warehouse=1 on returned products.
     *
     * @param  array<string, mixed>  $product
     */
    public static function isWarehouseProduct(array $product): bool
    {
        return (int) ($product['from_warehouse'] ?? 0) === 1;
    }

    /**
     * Step 1 connection test: warehouse filter passes when sample has from_warehouse products.
     *
     * @param  array<int, array<string, mixed>>  $products
     */
    public static function connectionTestPassed(array $products, bool $productReadSuccess): bool
    {
        if (! $productReadSuccess || $products === []) {
            return false;
        }

        $first = $products[0] ?? null;

        return is_array($first) && self::isWarehouseProduct($first);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public static function filter(array $products, string $supplierFilter): array
    {
        return array_values(array_filter(
            $products,
            fn (array $product) => self::isWarehouseProduct($product)
        ));
    }
}
