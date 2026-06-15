<?php

namespace Tests\Unit;

use App\Services\OpenCart\SupplierProductFilter;
use PHPUnit\Framework\TestCase;

class SupplierProductFilterTest extends TestCase
{
    public function test_connection_test_passes_with_from_warehouse_product(): void
    {
        $products = [
            ['source_product_id' => '101', 'from_warehouse' => 1, 'name' => 'Sample'],
        ];

        $this->assertTrue(SupplierProductFilter::connectionTestPassed($products, true));
    }

    public function test_connection_test_fails_without_from_warehouse_flag(): void
    {
        $products = [
            ['source_product_id' => '101', 'from_warehouse' => 0, 'name' => 'Sample'],
        ];

        $this->assertFalse(SupplierProductFilter::connectionTestPassed($products, true));
    }

    public function test_filter_keeps_only_warehouse_products(): void
    {
        $products = [
            ['from_warehouse' => 1],
            ['from_warehouse' => 0],
            ['from_warehouse' => 1],
        ];

        $this->assertCount(2, SupplierProductFilter::filter($products, 'ex-a'));
    }
}
