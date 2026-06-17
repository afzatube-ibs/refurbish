<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Services\OpenCart\OpenCartImageContext;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductMapCatalogService;

trait SeedsProductMapCatalog
{
    /**
     * @param  array<int, array<string, mixed>>|null  $products
     * @return array<int, array<string, mixed>>
     */
    protected function seedProductMapCatalog(?array $products = null): array
    {
        if ($products === null) {
            $products = $this->buildSampleCatalogProducts();
        }

        app(ProductMapCatalogService::class)->upsertProducts($products);

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildSampleCatalogProducts(): array
    {
        $service = new class(app(\App\Services\OpenCart\OpenCartHttpClient::class), app(\App\Services\OpenCart\ConnectionService::class)) extends ProductPreviewService
        {
            public function buildSample(): array
            {
                $product = $this->normalizeProduct([
                    'product_id' => '9509',
                    'model' => 'PARENT-9509',
                    'ibs_model' => 'IBS-9509',
                    'image' => 'catalog/p.jpg',
                    'stock' => 12,
                    'from_warehouse' => 1,
                    'options' => [
                        [
                            'model' => 'PARENT-9509-1',
                            'quantity' => 3,
                            'image' => 'catalog/opt.jpg',
                        ],
                    ],
                ], OpenCartImageContext::fromStoreUrl('https://example.com'));

                return $this->applyHealthRules([$product]);
            }
        };

        return $service->buildSample();
    }

    protected function loadAndConfirmCatalog(?User $user = null): void
    {
        $user = $user ?? $this->adminUser();

        $this->actingAs($user)
            ->post(route('product-map.load'))
            ->assertRedirect(route('product-map.index'));

        $this->actingAs($user)
            ->post(route('product-map.load.confirm'))
            ->assertRedirect(route('product-map.index'));
    }
}
