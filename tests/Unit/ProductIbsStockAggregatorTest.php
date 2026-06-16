<?php

namespace Tests\Unit;

use App\Services\ProductMap\ProductIbsStockAggregator;
use Tests\TestCase;

class ProductIbsStockAggregatorTest extends TestCase
{
    public function test_simple_product_uses_parent_stock(): void
    {
        $this->assertSame(15, ProductIbsStockAggregator::forProduct([
            'ibs_stock' => 15,
            'options' => [],
        ]));
    }

    public function test_simple_product_without_stock_returns_null(): void
    {
        $this->assertNull(ProductIbsStockAggregator::forProduct([
            'ibs_stock' => null,
            'options' => [],
        ]));
    }

    public function test_variable_product_sums_variant_stock_ignoring_nulls(): void
    {
        $this->assertSame(18, ProductIbsStockAggregator::forProduct([
            'options' => [
                ['ibs_stock' => 10],
                ['ibs_stock' => null],
                ['ibs_stock' => 8],
            ],
        ]));
    }

    public function test_variable_product_without_any_variant_stock_returns_null(): void
    {
        $this->assertNull(ProductIbsStockAggregator::forProduct([
            'options' => [
                ['ibs_stock' => null],
                ['ibs_stock' => null],
            ],
        ]));
    }

    public function test_variable_product_includes_zero_values_in_sum(): void
    {
        $this->assertSame(5, ProductIbsStockAggregator::forProduct([
            'options' => [
                ['ibs_stock' => 0],
                ['ibs_stock' => 5],
            ],
        ]));
    }
}
