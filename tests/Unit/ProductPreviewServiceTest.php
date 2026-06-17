<?php

namespace Tests\Unit;

use App\Services\OpenCart\OpenCartImageContext;
use App\Services\OpenCart\OpenCartMediaUrlResolver;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\OpenCart\SupplierProductFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeService(): ProductPreviewService
    {
        return new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends ProductPreviewService
        {
            public function dedupeKey(array $product): string
            {
                return $this->parentDedupeKey($product);
            }

            public function typeFor(array $product): string
            {
                return $this->resolveProductType($product);
            }

            public function optionsFor(array $product): array
            {
                return $this->extractRawOptions($product);
            }

            public function detectNewProductsForTest(array $fresh, array $existing): array
            {
                return $this->detectNewProducts($fresh, $existing);
            }

            public function appendNewProductsForTest(array $preview, array $new): array
            {
                return $this->appendNewProducts($preview, $new);
            }
        };
    }

    public function test_dedupe_key_prefers_product_id(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            'id:601',
            $service->dedupeKey(['product_id' => '601', 'source_product_id' => '999', 'model' => 'ABC'])
        );
    }

    public function test_type_is_variable_when_options_present_even_if_api_says_simple(): void
    {
        $service = $this->makeService();

        $this->assertSame('variable', $service->typeFor([
            'type' => 'simple',
            'options' => [
                ['option_name' => 'Color', 'option_value' => 'Green', 'model' => 'E-601-GREEN'],
            ],
        ]));
    }

    public function test_type_is_simple_without_options(): void
    {
        $service = $this->makeService();

        $this->assertSame('simple', $service->typeFor([
            'type' => 'variable',
            'options' => [],
        ]));
    }

    public function test_extracts_options_field(): void
    {
        $service = $this->makeService();

        $options = $service->optionsFor([
            'options' => [
                ['option_name' => 'Color', 'option_value' => 'Red', 'model' => 'R1'],
                ['option_name' => 'Color', 'option_value' => 'Red', 'model' => 'R1'],
            ],
        ]);

        $this->assertCount(1, $options);
    }

    public function test_warehouse_filter_only_keeps_flagged_products(): void
    {
        $products = [
            ['from_warehouse' => 1, 'product_id' => '1'],
            ['from_warehouse' => 0, 'product_id' => '2'],
        ];

        $filtered = SupplierProductFilter::filter($products, 'ex-a');

        $this->assertCount(1, $filtered);
        $this->assertSame('1', $filtered[0]['product_id']);
    }

    public function test_health_flags_listing_review_reasons(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends ProductPreviewService
        {
            public function healthFor(array $product, OpenCartImageContext $context): array
            {
                $normalized = $this->normalizeProduct($product, $context);

                return $this->applyHealthRules([$normalized])[0]['health'];
            }

            public function optionHealthFor(array $product, OpenCartImageContext $context): array
            {
                $normalized = $this->normalizeProduct($product, $context);

                return $this->applyHealthRules([$normalized])[0]['options'][0]['health'];
            }
        };

        $context = OpenCartImageContext::fromStoreUrl('https://www.staging.lokkisona.com');

        $health = $service->healthFor([
            'model' => 'PARENT-1',
            'image' => '',
            'stock' => 5,
            'rate' => 10.0,
            'ibs_stock' => 5,
            'options' => [],
        ], $context);

        $this->assertContains('Missing IBS model', $health['issues']);
        $this->assertContains('Missing main image', $health['issues']);

        $optionHealth = $service->optionHealthFor([
            'model' => 'PARENT-2',
            'ibs_model' => 'IBS-PARENT-2',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'rate' => 10.0,
            'options' => [
                ['option_name' => 'Color', 'option_value' => 'Red', 'model' => 'VAR-1', 'ibs_stock' => 8],
            ],
        ], $context);

        $this->assertContains('Missing IBS model', $optionHealth['issues']);
        $this->assertContains('Missing option image', $optionHealth['issues']);
    }

    public function test_local_health_priority_critical_over_warning(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends ProductPreviewService
        {
            public function healthFor(array $product): array
            {
                $normalized = $this->normalizeProduct($product, OpenCartImageContext::fromStoreUrl('https://example.com'));

                return $this->applyHealthRules([$normalized])[0]['health'];
            }
        };

        $health = $service->healthFor([
            'model' => 'SIMPLE-1',
            'ibs_model' => 'IBS-SIMPLE-1',
            'image' => 'catalog/p.jpg',
            'stock' => 10,
            'options' => [],
        ]);

        $this->assertSame('critical', $health['status']);
        $this->assertContains('Missing Rate', $health['issues']);
    }

    public function test_alert_uses_ibs_stock_not_oc_stock(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends ProductPreviewService
        {
            public function healthFor(array $product): array
            {
                $context = OpenCartImageContext::fromStoreUrl('https://example.com');
                $normalized = $this->normalizeProduct($product, $context);
                $merged = $this->overlayLocalFields(
                    $normalized,
                    $product,
                    ['rate', 'ibs_stock', 'ibs_model', 'sm_model', 'low_warning', 'product_category'],
                );

                return $this->applyHealthRules([$merged])[0]['health'];
            }
        };

        $health = $service->healthFor([
            'model' => 'SIMPLE-2',
            'ibs_model' => 'IBS-SIMPLE-2',
            'image' => 'catalog/p.jpg',
            'stock' => 100,
            'rate' => 25.0,
            'ibs_stock' => 2,
            'product_category' => 'Chair',
            'options' => [],
        ]);

        $this->assertSame('alert', $health['status']);
        $this->assertContains('Low stock', $health['issues']);
    }

    public function test_store_image_url_uses_image_prefix_and_encodes_spaces(): void
    {
        $resolver = new OpenCartMediaUrlResolver();
        $context = OpenCartImageContext::fromStoreUrl('https://www.staging.lokkisona.com');

        $this->assertSame(
            'https://www.staging.lokkisona.com/image/catalog/Products/toys/560.jpg',
            $resolver->resolveProductImage(['image' => 'catalog/Products/toys/560.jpg'], $context)
        );

        $this->assertSame(
            'https://www.staging.lokkisona.com/image/catalog/Products/my%20toy/560.jpg',
            $resolver->resolveOptionImage(['image' => 'catalog/Products/my toy/560.jpg'], $context)
        );
    }

    public function test_detect_new_products_skips_existing_ids(): void
    {
        $service = $this->makeService();

        $existing = [
            ['product_id' => '1', 'model' => 'A'],
            ['product_id' => '2', 'model' => 'B'],
        ];
        $fresh = [
            ['product_id' => '2', 'model' => 'B-UPDATED'],
            ['product_id' => '3', 'model' => 'C'],
        ];

        $new = $service->detectNewProductsForTest($fresh, $existing);

        $this->assertCount(1, $new);
        $this->assertSame('3', $service->productKey($new[0]));
    }

    public function test_append_new_products_avoids_duplicates(): void
    {
        $service = $this->makeService();

        $preview = [
            'products' => [
                ['product_id' => '1', 'model' => 'A', 'options' => []],
            ],
            'meta' => [],
            'summary' => [],
        ];

        $merged = $service->appendNewProductsForTest($preview, [
            ['product_id' => '1', 'model' => 'A-DUP', 'options' => []],
            ['product_id' => '2', 'model' => 'B', 'options' => []],
        ]);

        $this->assertCount(2, $merged['products']);
        $this->assertSame('A', $merged['products'][0]['model']);
        $this->assertSame('B', $merged['products'][1]['model']);
    }

    public function test_refresh_existing_preview_preserves_local_rate(): void
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends ProductPreviewService
        {
            public function loadPreview(): array
            {
                return [
                    'products' => [
                        [
                            'product_id' => '99',
                            'model' => 'FRESH',
                            'rate' => null,
                            'options' => [],
                        ],
                    ],
                    'diagnostics' => ['raw_product_count' => 1],
                ];
            }
        };

        $existing = [
            'products' => [
                [
                    'product_id' => '99',
                    'model' => 'LOCAL',
                    'rate' => 55.5,
                    'options' => [],
                ],
            ],
            'meta' => [],
            'summary' => [],
        ];

        $refreshed = $service->refreshExistingPreview($existing);

        $this->assertSame('FRESH', $refreshed['products'][0]['model']);
        $this->assertSame(55.5, $refreshed['products'][0]['rate']);
        $this->assertCount(1, $refreshed['products']);
    }

    public function test_refresh_existing_preview_requires_loaded_products(): void
    {
        $service = $this->makeService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No products loaded yet. Please use Load Products first.');

        $service->refreshExistingPreview(['products' => []]);
    }
}
