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
                return $this->normalizeProduct($product, $context)['health'];
            }
        };

        $health = $service->healthFor([
            'model' => '',
            'image' => '',
            'stock' => 5,
            'options' => [
                ['option_name' => 'Color', 'option_value' => 'Red'],
            ],
        ], OpenCartImageContext::fromStoreUrl('https://www.staging.lokkisona.com'));

        $this->assertContains('Missing IBS model', $health['issues']);
        $this->assertContains('Missing image', $health['issues']);
        $this->assertContains('Missing variant model', $health['issues']);
        $this->assertContains('Missing option image', $health['issues']);
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
}
