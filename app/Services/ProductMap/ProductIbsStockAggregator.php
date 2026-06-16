<?php

namespace App\Services\ProductMap;

class ProductIbsStockAggregator
{
    /**
     * @param  array<string, mixed>  $product
     */
    public static function forProduct(array $product): ?int
    {
        $options = is_array($product['options'] ?? null)
            ? $product['options']
            : (is_array($product['variants'] ?? null) ? $product['variants'] : []);

        if ($options === []) {
            if (array_key_exists('ibs_stock', $product) && $product['ibs_stock'] !== null && $product['ibs_stock'] !== '') {
                return (int) $product['ibs_stock'];
            }

            return null;
        }

        $sum = 0;
        $hasAny = false;

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            if (! array_key_exists('ibs_stock', $option) || $option['ibs_stock'] === null || $option['ibs_stock'] === '') {
                continue;
            }

            $sum += (int) $option['ibs_stock'];
            $hasAny = true;
        }

        return $hasAny ? $sum : null;
    }
}
