<?php

namespace App\Services\ProductMap;

use App\Models\ProductMap\ProductMapProduct;
use App\Models\Supplier;
use App\Services\OpenCart\ProductPreviewService;
use Illuminate\Support\Carbon;

class ProductMapCatalogService
{
    /** @var array<int, string> */
    private const LOCAL_PARENT_FIELDS = [
        'rate',
        'ibs_stock',
        'sm_model',
        'low_warning',
        'product_category',
        'supplier_cost',
        'health',
        '_sync_status',
    ];

    /** @var array<int, string> */
    private const LOCAL_OPTION_FIELDS = [
        'rate',
        'ibs_stock',
        'sm_model',
        'low_warning',
        'supplier_cost',
        'rate_override',
        'health',
    ];

    /** @var array<int, string> */
    private const OC_PARENT_FIELDS = [
        'product_id',
        'oc_product_id',
        'source_product_id',
        'image',
        'image_path',
        'lk_model',
        'model',
        'ibs_model',
        'stock',
        'name',
        'type',
        'from_warehouse',
    ];

    /** @var array<int, string> */
    private const OC_OPTION_FIELDS = [
        'option_name',
        'option_value',
        'lk_model',
        'model',
        'variant_key',
        'ibs_model',
        'quantity',
        'stock',
        'image',
        'image_path',
    ];

    public function __construct(
        private readonly ProductControlSupplierResolver $supplierResolver,
        private readonly ProductControlMergeService $controlMergeService,
        private readonly ProductPreviewService $previewService,
    ) {}

    public function hasProducts(?Supplier $supplier = null): bool
    {
        $supplier ??= $this->supplierResolver->resolve();

        return ProductMapProduct::query()
            ->where('supplier_id', $supplier->id)
            ->exists();
    }

    public function hasProductsSafely(): bool
    {
        try {
            return $this->hasProducts();
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function productCount(?Supplier $supplier = null): int
    {
        $supplier ??= $this->supplierResolver->resolve();

        return ProductMapProduct::query()
            ->where('supplier_id', $supplier->id)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPreview(?array $syncMeta = null, ?array $syncDiagnostics = null): array
    {
        $supplier = $this->supplierResolver->resolve();
        $rows = ProductMapProduct::query()
            ->where('supplier_id', $supplier->id)
            ->orderBy('source_product_id')
            ->get();

        $products = $rows
            ->map(fn (ProductMapProduct $row) => $this->hydrateProductFromRow($row))
            ->values()
            ->all();

        $lastSynced = $rows->max('last_synced_at');
        $preview = [
            'products' => $products,
            'activity' => [],
            'meta' => [
                'source' => 'DropFlow database',
                'read_only' => true,
                'warehouse_count' => count($products),
                'loaded_at' => ($lastSynced instanceof Carbon ? $lastSynced : now())->toIso8601String(),
                'has_local_edits' => false,
            ],
            'summary' => [],
            'diagnostics' => [
                'source' => 'database',
                'warehouse_product_count' => count($products),
            ],
        ];

        if (is_array($syncMeta) && $syncMeta !== []) {
            $preview['meta'] = array_merge($preview['meta'], $syncMeta);
        }

        if (is_array($syncDiagnostics) && $syncDiagnostics !== []) {
            $preview['diagnostics'] = array_merge($preview['diagnostics'], $syncDiagnostics);
        }

        $preview = $this->controlMergeService->mergeIntoPreview($preview);

        return $this->previewService->refreshPreviewState($preview);
    }

    /**
     * @param  array<int, array<string, mixed>>  $freshProducts
     * @return array<int, array<string, mixed>>
     */
    public function detectSyncChanges(array $freshProducts): array
    {
        $supplier = $this->supplierResolver->resolve();
        $existing = ProductMapProduct::query()
            ->where('supplier_id', $supplier->id)
            ->get()
            ->keyBy('source_product_id');

        $changes = [];

        foreach ($freshProducts as $product) {
            if (! is_array($product)) {
                continue;
            }

            $productId = $this->productId($product);

            if ($productId === '') {
                continue;
            }

            $canonical = $this->canonicalOcSnapshot($product);
            $fingerprint = $this->ocFingerprint($canonical);
            $row = $existing->get($productId);

            if ($row === null) {
                $product['_sync_status'] = 'new';
                $changes[] = $product;

                continue;
            }

            if ($row->oc_fingerprint !== $fingerprint) {
                $product['_sync_status'] = 'changed';
                $changes[] = $product;
            }
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, array<string, mixed>>  $rawSnapshots
     */
    public function upsertProducts(array $products, array $rawSnapshots = []): int
    {
        $supplier = $this->supplierResolver->resolve();
        $saved = 0;
        $syncedAt = now();

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $productId = $this->productId($product);

            if ($productId === '') {
                continue;
            }

            $canonical = $this->canonicalOcSnapshot($product);

            ProductMapProduct::query()->updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'source_product_id' => $productId,
                ],
                [
                    'oc_snapshot' => $canonical,
                    'source_product_snapshot' => $rawSnapshots[$productId] ?? null,
                    'oc_fingerprint' => $this->ocFingerprint($canonical),
                    'last_synced_at' => $syncedAt,
                ],
            );

            $saved++;
        }

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function stripLocalFields(array $product): array
    {
        foreach (self::LOCAL_PARENT_FIELDS as $field) {
            unset($product[$field]);
        }

        $options = is_array($product['options'] ?? null) ? $product['options'] : [];

        foreach ($options as $index => $option) {
            if (! is_array($option)) {
                continue;
            }

            foreach (self::LOCAL_OPTION_FIELDS as $field) {
                unset($option[$field]);
            }

            $options[$index] = $option;
        }

        $product['options'] = $options;
        $product['variants'] = $options;

        return $product;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public function ocFingerprint(array $product): string
    {
        $canonical = array_key_exists('options', $product)
            ? $this->canonicalOcSnapshot($product)
            : $product;

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function canonicalOcSnapshot(array $product): array
    {
        $product = $this->stripLocalFields($product);
        $snapshot = [];

        foreach (self::OC_PARENT_FIELDS as $field) {
            if (array_key_exists($field, $product)) {
                $snapshot[$field] = $product[$field];
            }
        }

        $options = is_array($product['options'] ?? null) ? $product['options'] : [];
        $snapshot['options'] = array_map(function (array $option) {
            $row = [];

            foreach (self::OC_OPTION_FIELDS as $field) {
                if (array_key_exists($field, $option)) {
                    $row[$field] = $option[$field];
                }
            }

            return $row;
        }, array_values(array_filter($options, 'is_array')));

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    protected function hydrateProductFromRow(ProductMapProduct $row): array
    {
        $product = is_array($row->oc_snapshot) ? $row->oc_snapshot : [];
        $product['product_id'] = (string) ($product['product_id'] ?? $row->source_product_id);
        $product['oc_product_id'] = (string) ($product['oc_product_id'] ?? $row->source_product_id);
        $product['source_product_id'] = $row->source_product_id;
        $product['rate'] = null;
        $product['ibs_stock'] = null;
        $product['sm_model'] = '';
        $product['supplier_cost'] = null;
        $product['low_warning'] = (int) ($product['low_warning'] ?? ProductPreviewService::DEFAULT_LOW_WARNING);
        $product['product_category'] = null;

        $options = is_array($product['options'] ?? null) ? $product['options'] : [];

        foreach ($options as $index => $option) {
            if (! is_array($option)) {
                continue;
            }

            $option['rate'] = null;
            $option['ibs_stock'] = null;
            $option['sm_model'] = '';
            $option['supplier_cost'] = null;
            $option['rate_override'] = null;
            $options[$index] = $option;
        }

        $product['options'] = $options;
        $product['variants'] = $options;

        return $product;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function productId(array $product): string
    {
        return trim((string) ($product['product_id'] ?? $product['source_product_id'] ?? $product['oc_product_id'] ?? ''));
    }
}
