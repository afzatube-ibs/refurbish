<?php

namespace Tests\Unit;

use App\Services\OpenCart\OpenCartImageContext;
use App\Services\OpenCart\OpenCartMediaUrlResolver;
use App\Services\OpenCart\OpenCartOptionImageResolver;
use Tests\TestCase;

class OpenCartMediaUrlResolverTest extends TestCase
{
    private OpenCartMediaUrlResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new OpenCartMediaUrlResolver();
    }

    public function test_product_image_url_takes_priority_over_path(): void
    {
        $context = OpenCartImageContext::fromStoreUrl('https://store.example.com');

        $url = $this->resolver->resolveProductImage([
            'image_url' => 'https://cdn.example.com/catalog/ready.jpg',
            'image' => 'catalog/ignored.jpg',
        ], $context);

        $this->assertSame('https://cdn.example.com/catalog/ready.jpg', $url);
    }

    public function test_option_image_url_takes_priority_over_poip_path(): void
    {
        $context = OpenCartImageContext::fromStoreUrl('https://store.example.com');

        $url = $this->resolver->resolveOptionImage([
            'option_image_url' => 'https://cdn.example.com/catalog/opt.jpg',
            'image' => 'catalog/poip/opt.jpg',
        ], $context);

        $this->assertSame('https://cdn.example.com/catalog/opt.jpg', $url);
    }

    public function test_image_base_url_is_used_before_store_url_for_paths(): void
    {
        $context = OpenCartImageContext::fromStoreUrl('https://store.example.com')
            ->mergeApiResponse(['image_base_url' => 'https://www.staging.lokkisona.com']);

        $url = $this->resolver->resolveProductImage([
            'image' => 'catalog/Products/toys/560.jpg',
        ], $context);

        $this->assertSame(
            'https://www.staging.lokkisona.com/image/catalog/Products/toys/560.jpg',
            $url
        );
    }

    public function test_path_fallback_uses_store_url_when_no_image_base_url(): void
    {
        $context = OpenCartImageContext::fromStoreUrl('https://www.staging.lokkisona.com');

        $url = $this->resolver->resolveProductImage([
            'image' => 'catalog/Products/toys/560.jpg',
        ], $context);

        $this->assertSame(
            'https://www.staging.lokkisona.com/image/catalog/Products/toys/560.jpg',
            $url
        );
    }

    public function test_spaces_in_path_are_encoded(): void
    {
        $context = OpenCartImageContext::fromStoreUrl('https://www.staging.lokkisona.com');

        $url = $this->resolver->resolveOptionImage([
            'image' => 'catalog/Products/my toy/560.jpg',
        ], $context);

        $this->assertSame(
            'https://www.staging.lokkisona.com/image/catalog/Products/my%20toy/560.jpg',
            $url
        );
    }

    public function test_option_image_resolver_extracts_poip_image_path(): void
    {
        $this->assertSame(
            'catalog/poip/green.jpg',
            OpenCartOptionImageResolver::extractFromPayload([
                'option_name' => 'Color',
                'option_value' => 'Green',
                'image' => 'catalog/poip/green.jpg',
            ])
        );
    }
}
