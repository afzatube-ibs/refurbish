<?php

namespace App\Services\OpenCart;

use App\Models\Connection;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductVariant;
use RuntimeException;

class ProductSyncService
{
    protected bool $lastHasMore = false;

    public function __construct(
        protected OpenCartHttpClient $client
    ) {}

    /**
     * @return array{imported: int, has_more: bool, page: int}
     */
    public function syncNewBatch(): array
    {
        app(ConnectionService::class)->assertSyncAllowed();
        $connection = Connection::getInstance();
        $supplier = $this->resolveSupplier($connection);
        $batchSize = (int) config('dropflow.product_batch_size', 50);

        $response = $this->client->get($connection->product_api_endpoint, [
            'page' => $connection->product_sync_page,
            'limit' => $batchSize,
        ]);

        $imported = 0;

        foreach (SupplierProductFilter::filter($response['products'] ?? [], $connection->supplier_filter) as $productData) {
            $sourceId = (string) ($productData['source_product_id'] ?? '');

            if ($sourceId === '') {
                continue;
            }

            $exists = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('source_product_id', $sourceId)
                ->exists();

            if ($exists) {
                continue;
            }

            $product = SupplierProduct::query()->create([
                'supplier_id' => $supplier->id,
                'source_product_id' => $sourceId,
                'image' => $productData['image'] ?? null,
                'model' => (string) ($productData['model'] ?? ''),
                'name' => (string) ($productData['name'] ?? ''),
                'type' => $productData['type'] ?? null,
                'stock' => (int) ($productData['stock'] ?? 0),
                'last_synced_at' => now(),
            ]);

            $this->syncVariants($product, $productData['variants'] ?? []);
            $imported++;
        }

        $this->lastHasMore = (bool) ($response['pagination']['has_more'] ?? false);

        if ($this->lastHasMore) {
            $connection->product_sync_page++;
        }

        $connection->last_product_sync_at = now();
        $connection->save();

        return [
            'imported' => $imported,
            'has_more' => $this->lastHasMore,
            'page' => $connection->product_sync_page,
        ];
    }

    /**
     * @return array{refreshed: int, has_more: bool, page: int}
     */
    public function refreshBatch(): array
    {
        app(ConnectionService::class)->assertSyncAllowed();
        $connection = Connection::getInstance();
        $supplier = $this->resolveSupplier($connection);
        $batchSize = (int) config('dropflow.product_batch_size', 50);

        $response = $this->client->get($connection->product_api_endpoint, [
            'page' => $connection->product_sync_page,
            'limit' => $batchSize,
        ]);

        $refreshed = 0;

        foreach (SupplierProductFilter::filter($response['products'] ?? [], $connection->supplier_filter) as $productData) {
            $sourceId = (string) ($productData['source_product_id'] ?? '');

            if ($sourceId === '') {
                continue;
            }

            $product = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('source_product_id', $sourceId)
                ->first();

            if (! $product) {
                continue;
            }

            $product->update([
                'image' => $productData['image'] ?? null,
                'model' => (string) ($productData['model'] ?? $product->model),
                'name' => (string) ($productData['name'] ?? $product->name),
                'type' => $productData['type'] ?? $product->type,
                'stock' => (int) ($productData['stock'] ?? $product->stock),
                'last_synced_at' => now(),
            ]);

            $this->syncVariants($product, $productData['variants'] ?? []);
            $refreshed++;
        }

        $this->lastHasMore = (bool) ($response['pagination']['has_more'] ?? false);

        if ($this->lastHasMore) {
            $connection->product_sync_page++;
        }

        $connection->last_product_sync_at = now();
        $connection->save();

        return [
            'refreshed' => $refreshed,
            'has_more' => $this->lastHasMore,
            'page' => $connection->product_sync_page,
        ];
    }

    public function hasMore(): bool
    {
        return $this->lastHasMore;
    }

    public function resetCursor(): void
    {
        $connection = Connection::getInstance();
        $connection->product_sync_page = 1;
        $connection->save();
        $this->lastHasMore = false;
    }

    protected function resolveSupplier(Connection $connection): Supplier
    {
        $supplier = Supplier::query()
            ->where('is_active', true)
            ->where(function ($query) use ($connection) {
                $query->where('code', $connection->supplier_filter)
                    ->orWhere('code', strtoupper($connection->supplier_filter));
            })
            ->first();

        if ($supplier) {
            return $supplier;
        }

        $fallback = Supplier::query()->where('is_active', true)->first();

        if (! $fallback) {
            throw new RuntimeException('No active supplier configured for product sync.');
        }

        return $fallback;
    }

    protected function syncVariants(SupplierProduct $product, array $variants): void {
        foreach ($variants as $variantData) {
            $key = (string) ($variantData['source_variant_key'] ?? '');

            if ($key === '') {
                continue;
            }

            SupplierProductVariant::query()->updateOrCreate(
                [
                    'supplier_product_id' => $product->id,
                    'source_variant_key' => $key,
                ],
                [
                    'option_label' => (string) ($variantData['option_label'] ?? $key),
                    'option_image' => $variantData['option_image'] ?? null,
                    'stock' => (int) ($variantData['stock'] ?? 0),
                ]
            );
        }
    }
}
