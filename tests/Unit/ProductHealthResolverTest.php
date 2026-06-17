<?php

namespace Tests\Unit;

use App\Services\ProductMap\ProductHealthResolver;
use Tests\TestCase;

class ProductHealthResolverTest extends TestCase
{
    protected ProductHealthResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ProductHealthResolver();
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function parentHealth(array $product): array
    {
        $options = is_array($product['options'] ?? null) ? $product['options'] : [];

        return $this->resolver->assessParentHealth(
            $product,
            $options,
            (int) ($product['low_warning'] ?? ProductHealthResolver::DEFAULT_LOW_WARNING),
            $this->resolver->findDuplicateIbsModels([$product]),
        );
    }

    public function test_simple_product_with_core_fields_counts_as_ready_even_without_image(): void
    {
        $health = $this->parentHealth([
            'ibs_model' => 'IBS-SIMPLE-READY',
            'image' => '',
            'stock' => 10,
            'rate' => 12.5,
            'ibs_stock' => 20,
            'product_category' => 'Chair',
            'options' => [],
        ]);

        $this->assertSame('ok', $health['status']);
        $this->assertContains('Missing main image', $health['issues']);
    }

    public function test_negative_oc_stock_is_critical(): void
    {
        $health = $this->parentHealth([
            'ibs_model' => 'IBS-NEG-OC',
            'image' => 'catalog/p.jpg',
            'stock' => -2,
            'rate' => 10.0,
            'ibs_stock' => 8,
            'product_category' => 'Chair',
            'options' => [],
        ]);

        $this->assertSame('critical', $health['status']);
        $this->assertContains('Negative Stock -', $health['issues']);
    }

    public function test_negative_ibs_stock_is_critical(): void
    {
        $health = $this->parentHealth([
            'ibs_model' => 'IBS-NEG-IBS',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'rate' => 10.0,
            'ibs_stock' => -1,
            'product_category' => 'Chair',
            'options' => [],
        ]);

        $this->assertSame('critical', $health['status']);
        $this->assertContains('Negative Stock -', $health['issues']);
    }

    public function test_variable_parent_ready_when_all_variants_have_rate_and_stock_without_parent_rate(): void
    {
        $health = $this->parentHealth([
            'ibs_model' => 'IBS-VAR-PARENT',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'rate' => null,
            'product_category' => 'Chair',
            'options' => [
                ['stock' => 10, 'image' => 'catalog/o1.jpg', 'ibs_model' => 'IBS-V1', 'rate' => 5.0, 'ibs_stock' => 10],
                ['stock' => 8, 'image' => 'catalog/o2.jpg', 'ibs_model' => 'IBS-V2', 'rate' => 6.0, 'ibs_stock' => 12],
            ],
        ]);

        $this->assertSame('ok', $health['status']);
    }

    public function test_variable_parent_review_when_some_variants_missing_rate_or_stock(): void
    {
        $health = $this->parentHealth([
            'ibs_model' => 'IBS-VAR-PARTIAL',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'product_category' => 'Chair',
            'options' => [
                ['stock' => 10, 'image' => 'catalog/o1.jpg', 'ibs_model' => 'IBS-V1', 'rate' => 5.0, 'ibs_stock' => 10],
                ['stock' => 8, 'image' => 'catalog/o2.jpg', 'ibs_model' => 'IBS-V2', 'ibs_stock' => 12],
            ],
        ]);

        $this->assertSame('needs_attention', $health['status']);
        $this->assertContains('Some variants missing rate or stock', $health['issues']);
    }

    public function test_variable_parent_critical_when_all_variants_missing_rate_and_stock(): void
    {
        $health = $this->parentHealth([
            'ibs_model' => 'IBS-VAR-EMPTY',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'product_category' => 'Chair',
            'options' => [
                ['stock' => 10, 'image' => 'catalog/o1.jpg', 'ibs_model' => 'IBS-V1'],
                ['stock' => 8, 'image' => 'catalog/o2.jpg', 'ibs_model' => 'IBS-V2'],
            ],
        ]);

        $this->assertSame('critical', $health['status']);
        $this->assertContains('Missing Rate', $health['issues']);
    }

    public function test_missing_category_is_warning_not_critical(): void
    {
        $health = $this->parentHealth([
            'ibs_model' => 'IBS-NO-CAT',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'rate' => 10.0,
            'ibs_stock' => 20,
            'product_category' => null,
            'options' => [],
        ]);

        $this->assertSame('warning', $health['status']);
        $this->assertContains('Missing category', $health['issues']);
        $this->assertNotSame('critical', $health['status']);
    }

    public function test_dashboard_counts_ready_needs_work_and_variants(): void
    {
        $products = $this->resolver->applyToProducts([
            [
                'ibs_model' => 'IBS-A',
                'stock' => 10,
                'rate' => 5.0,
                'ibs_stock' => 10,
                'low_warning' => 5,
                'product_category' => 'Chair',
                'options' => [],
            ],
            [
                'ibs_model' => 'IBS-B',
                'stock' => 10,
                'rate' => null,
                'ibs_stock' => 10,
                'low_warning' => 5,
                'product_category' => 'Chair',
                'options' => [],
            ],
            [
                'ibs_model' => 'IBS-C',
                'stock' => 10,
                'rate' => null,
                'ibs_stock' => null,
                'low_warning' => 5,
                'product_category' => 'Chair',
                'options' => [
                    ['stock' => 5, 'image' => 'catalog/o.jpg', 'ibs_model' => 'IBS-C1', 'rate' => 5.0, 'ibs_stock' => 5],
                    ['stock' => 5, 'image' => 'catalog/o2.jpg', 'ibs_model' => 'IBS-C2', 'rate' => 5.0, 'ibs_stock' => 5],
                ],
            ],
        ]);

        $counts = $this->resolver->buildDashboardCounts($products);

        $this->assertSame(2, $counts['health_ok']);
        $this->assertSame(1, $counts['health_needs_work']);
        $this->assertSame(2, $counts['variant_rows']);
    }
}
