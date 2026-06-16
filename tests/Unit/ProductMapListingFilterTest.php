<?php

namespace Tests\Unit;

use App\Services\ProductMap\ProductMapListingFilter;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductMapListingFilterTest extends TestCase
{
    public function test_filters_by_search_query_and_type(): void
    {
        $filter = new ProductMapListingFilter;
        $products = [
            0 => ['model' => 'ABC-1', 'options' => [], 'health' => ['status' => 'ok']],
            1 => ['model' => 'XYZ-9', 'options' => [['model' => 'V1']], 'health' => ['status' => 'ok']],
        ];

        $filtered = $filter->apply($products, [
            'q' => 'abc',
            'category' => '',
            'type' => 'all',
            'health' => 'all',
        ]);

        $this->assertSame([0], array_keys($filtered));

        $variableOnly = $filter->apply($products, [
            'q' => '',
            'category' => '',
            'type' => 'variable',
            'health' => 'all',
        ]);

        $this->assertSame([1], array_keys($variableOnly));
    }

    public function test_resolve_per_page_from_request(): void
    {
        config(['dropflow.product_preview_page_size' => 20]);

        $filter = new ProductMapListingFilter;
        $resolved = $filter->resolveFromRequest(Request::create('/product-map', 'GET', ['per_page' => '50']));

        $this->assertSame(50, $resolved['per_page']);
    }
}
