<?php

namespace App\Services\OpenCart;

use App\Models\Connection;
use RuntimeException;

class ProductPreviewService
{
    public function __construct(
        private readonly OpenCartHttpClient $client,
        private readonly ConnectionService $connectionService,
        private readonly OpenCartMediaUrlResolver $mediaUrlResolver = new OpenCartMediaUrlResolver(),
    ) {}

    /**
     * @return array{
     *     products: array<int, array<string, mixed>>,
     *     meta: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     diagnostics: array<string, mixed>
     * }
     */
    public function loadPreview(): array
    {
        $connection = $this->connectionService->getActive();
        $this->assertPreviewAllowed($connection);

        $pageSize = $this->pageBatchSize();
        $endpoint = IbsRouteResolver::normalizeStoredEndpoint(
            $connection->product_api_endpoint,
            'products'
        );

        $collection = $this->fetchWarehouseParents($endpoint, $pageSize, $connection);
        $products = array_map(
            fn (array $product) => $this->normalizeProduct($product, $collection['image_context']),
            $collection['parents']
        );

        $summary = $this->buildSummary($collection['raw_seen'], $products);

        return [
            'products' => $products,
            'meta' => [
                'page_size' => $pageSize,
                'pages_fetched' => $collection['pages_fetched'],
                'last_page' => $collection['last_page'],
                'has_next' => $collection['has_next'],
                'total_reported' => $collection['total_reported'],
                'raw_rows_seen' => $collection['raw_rows_seen'],
                'warehouse_rows_seen' => $collection['warehouse_rows_seen'],
                'duplicates_skipped' => $collection['duplicates_skipped'],
                'warehouse_count' => count($products),
                'endpoint' => $endpoint,
                'read_only' => true,
                'loaded_at' => now()->toIso8601String(),
                'source' => 'LK (OpenCart via IBS connector)',
                'mapping' => [
                    'master' => 'IBS Model',
                    'active' => 'LK Model',
                    'reserved' => 'SM Model',
                ],
                'image_base_url' => $collection['image_context']->imageBaseUrl,
                'image_resolve_base' => $collection['image_context']->effectiveBaseUrl(),
            ],
            'summary' => $summary,
            'diagnostics' => [
                'request' => [
                    'page_size' => $pageSize,
                    'endpoint' => $endpoint,
                    'warehouse_filter' => 'from_warehouse=1',
                    'pagination_until' => 'has_next=false',
                ],
                'pagination' => $collection['pagination_trace'],
                'raw_product_count' => $collection['raw_rows_seen'],
                'warehouse_product_count' => count($products),
                'duplicates_skipped' => $collection['duplicates_skipped'],
                'image_base_url' => $collection['image_context']->imageBaseUrl,
                'image_resolve_base' => $collection['image_context']->effectiveBaseUrl(),
                'sample_raw_product' => $collection['parents'][0] ?? null,
            ],
        ];
    }

    /**
     * @return array{
     *     parents: array<int, array<string, mixed>>,
     *     pages_fetched: int,
     *     last_page: int,
     *     has_next: bool,
     *     total_reported: int,
     *     raw_rows_seen: int,
     *     warehouse_rows_seen: int,
     *     duplicates_skipped: int,
     *     image_context: OpenCartImageContext,
     *     raw_seen: array<int, array<string, mixed>>,
     *     pagination_trace: array<int, array<string, mixed>>
     * }
     */
    protected function fetchWarehouseParents(string $endpoint, int $pageSize, Connection $connection): array
    {
        $parents = [];
        $seenParentKeys = [];
        $rawSeen = [];
        $paginationTrace = [];
        $page = 1;
        $pagesFetched = 0;
        $hasNext = true;
        $totalReported = 0;
        $rawRowsSeen = 0;
        $warehouseRowsSeen = 0;
        $duplicatesSkipped = 0;
        $maxPages = 20;
        $imageContext = OpenCartImageContext::fromStoreUrl((string) $connection->store_url);

        while ($pagesFetched < $maxPages && $hasNext) {
            $response = $this->client->get($endpoint, [
                'page' => $page,
                'limit' => $pageSize,
            ]);

            if (($response['success'] ?? false) !== true) {
                throw new RuntimeException('Product API did not return success.');
            }

            $imageContext = $imageContext->mergeApiResponse($response);

            $pagesFetched++;
            $rawProducts = is_array($response['products'] ?? null) ? $response['products'] : [];
            $pagination = is_array($response['pagination'] ?? null) ? $response['pagination'] : [];
            $hasNext = $this->responseHasNext($pagination, $rawProducts);
            $totalReported = (int) ($pagination['total'] ?? $totalReported);
            $effectiveLimit = (int) ($pagination['limit'] ?? $pageSize);

            $paginationTrace[] = [
                'page' => (int) ($pagination['page'] ?? $page),
                'limit' => $effectiveLimit,
                'returned' => count($rawProducts),
                'has_next' => $hasNext,
                'total' => $totalReported ?: null,
            ];

            foreach ($rawProducts as $rawProduct) {
                if (! is_array($rawProduct)) {
                    continue;
                }

                $rawRowsSeen++;
                $rawSeen[] = $rawProduct;

                if (! SupplierProductFilter::isWarehouseProduct($rawProduct)) {
                    continue;
                }

                $warehouseRowsSeen++;

                $parentKey = $this->parentDedupeKey($rawProduct);

                if ($parentKey === '') {
                    continue;
                }

                if (isset($seenParentKeys[$parentKey])) {
                    $duplicatesSkipped++;

                    continue;
                }

                $seenParentKeys[$parentKey] = true;
                $parents[] = $this->prepareParentRow($rawProduct);
            }

            if ($rawProducts === []) {
                break;
            }

            if (! $hasNext) {
                break;
            }

            $page++;
        }

        return [
            'parents' => $parents,
            'pages_fetched' => $pagesFetched,
            'last_page' => $page,
            'has_next' => $hasNext,
            'total_reported' => $totalReported,
            'raw_rows_seen' => $rawRowsSeen,
            'warehouse_rows_seen' => $warehouseRowsSeen,
            'duplicates_skipped' => $duplicatesSkipped,
            'image_context' => $imageContext,
            'raw_seen' => $rawSeen,
            'pagination_trace' => $paginationTrace,
        ];
    }

    /**
     * @param  array<string, mixed>  $pagination
     * @param  array<int, mixed>  $rawProducts
     */
    protected function responseHasNext(array $pagination, array $rawProducts): bool
    {
        if (array_key_exists('has_next', $pagination)) {
            return (bool) $pagination['has_next'];
        }

        if (array_key_exists('has_more', $pagination)) {
            return (bool) $pagination['has_more'];
        }

        return $rawProducts !== [];
    }

    protected function assertPreviewAllowed(Connection $connection): void
    {
        if (! $connection->is_active || blank($connection->store_url)) {
            throw new RuntimeException('Save an active connection before loading products.');
        }

        if (blank($connection->api_token)) {
            throw new RuntimeException('API token is required to load products.');
        }
    }

    protected function pageBatchSize(): int
    {
        return min(20, max(1, (int) config('dropflow.product_preview_page_size', 20)));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function parentDedupeKey(array $product): string
    {
        foreach (['product_id', 'source_product_id'] as $field) {
            $value = trim((string) ($product[$field] ?? ''));

            if ($value !== '') {
                return 'id:'.$value;
            }
        }

        $model = $this->resolveParentModel($product);

        if ($model !== '') {
            return 'model:'.$model;
        }

        $name = trim((string) ($product['name'] ?? ''));

        return $name !== '' ? 'name:'.$name : '';
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function prepareParentRow(array $product): array
    {
        $options = $this->extractRawOptions($product);

        return array_merge($product, [
            'options' => $options,
            'type' => $this->resolveProductType($product),
        ]);
    }

    /**
     * Type rule: options count > 0 → variable, else simple.
     *
     * @param  array<string, mixed>  $product
     */
    protected function resolveProductType(array $product): string
    {
        $options = $product['options'] ?? null;

        return is_array($options) && count($options) > 0 ? 'variable' : 'simple';
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<int, array<string, mixed>>
     */
    protected function extractRawOptions(array $product): array
    {
        $options = $product['options'] ?? null;

        if (! is_array($options) || $options === []) {
            return [];
        }

        return $this->dedupeRawOptions($options);
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array<string, mixed>>
     */
    protected function dedupeRawOptions(array $options): array
    {
        $seen = [];
        $unique = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $name = trim((string) ($option['option_name'] ?? ''));
            $value = trim((string) ($option['option_value'] ?? ''));
            $model = trim((string) ($option['model'] ?? ''));
            $key = trim((string) ($option['source_variant_key'] ?? ''));

            if ($key === '') {
                $key = $name !== '' || $value !== ''
                    ? $name.':'.$value
                    : ($model !== '' ? $model : md5(json_encode($option)));
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $option;
        }

        return $unique;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $product, OpenCartImageContext $imageContext): array
    {
        $options = is_array($product['options'] ?? null) ? $product['options'] : [];
        $parentModel = $this->resolveParentModel($product);
        $normalizedOptions = array_map(
            fn (array $option) => $this->normalizeOption($option, $imageContext),
            $options
        );
        $type = $this->resolveProductType($product);
        $parentStock = $this->resolveParentStock($product);
        $parentImagePath = OpenCartOptionImageResolver::extractProductPath($product);
        $parentImage = $this->mediaUrlResolver->resolveProductImage($product, $imageContext);
        $health = $this->assessHealth($parentModel, $type, $parentStock, $parentImage, $normalizedOptions);

        return [
            'product_id' => (string) ($product['product_id'] ?? $product['source_product_id'] ?? ''),
            'oc_product_id' => (string) ($product['product_id'] ?? $product['source_product_id'] ?? ''),
            'source_product_id' => (string) ($product['source_product_id'] ?? $product['product_id'] ?? ''),
            'image' => $parentImage,
            'image_path' => $parentImagePath,
            'ibs_model' => $parentModel,
            'name' => (string) ($product['name'] ?? ''),
            'stock' => $parentStock,
            'type' => $type,
            'from_warehouse' => (int) ($product['from_warehouse'] ?? 0),
            'health' => $health,
            'options' => $normalizedOptions,
            'variants' => $normalizedOptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveParentStock(array $product): int
    {
        foreach (['stock', 'quantity'] as $field) {
            if (isset($product[$field]) && is_numeric($product[$field])) {
                return (int) $product[$field];
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveParentModel(array $product): string
    {
        foreach (['model', 'ibs_model', 'sku'] as $field) {
            $value = trim((string) ($product[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    protected function normalizeOption(array $option, OpenCartImageContext $imageContext): array
    {
        $name = trim((string) ($option['option_name'] ?? ''));
        $value = trim((string) ($option['option_value'] ?? ''));
        $model = trim((string) ($option['model'] ?? $option['option_model'] ?? ''));
        $stock = (int) ($option['quantity'] ?? $option['stock'] ?? $option['option_stock'] ?? 0);
        $imagePath = OpenCartOptionImageResolver::extractFromPayload($option);
        $image = $this->mediaUrlResolver->resolveOptionImage($option, $imageContext);

        return [
            'option_name' => $name !== '' ? $name : '—',
            'option_value' => $value !== '' ? $value : '—',
            'model' => $model !== '' ? $model : '—',
            'ibs_model' => $model !== '' ? $model : '—',
            'quantity' => $stock,
            'stock' => $stock,
            'image' => $image,
            'image_path' => $imagePath,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function assessHealth(
        string $parentModel,
        string $type,
        int $parentStock,
        ?string $parentImage,
        array $options
    ): array {
        $issues = [];

        if ($parentStock < 0) {
            $issues[] = 'Negative stock';
        }

        if (blank($parentImage)) {
            $issues[] = 'Missing image';
        }

        if ($parentModel === '') {
            $issues[] = 'Missing IBS model';
        }

        if ($type === 'variable') {
            foreach ($options as $option) {
                if ((int) ($option['stock'] ?? 0) < 0 && ! in_array('Negative stock', $issues, true)) {
                    $issues[] = 'Negative stock';
                }

                if (blank($option['image'] ?? null) && ! in_array('Missing option image', $issues, true)) {
                    $issues[] = 'Missing option image';
                }

                if (($option['ibs_model'] ?? '—') === '—' && ! in_array('Missing variant model', $issues, true)) {
                    $issues[] = 'Missing variant model';
                }
            }
        }

        return [
            'status' => $issues === [] ? 'ok' : 'needs_attention',
            'label' => $issues === [] ? 'OK' : 'Review',
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawProducts
     * @param  array<int, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    protected function buildSummary(array $rawProducts, array $products): array
    {
        $variableCount = 0;
        $warehouseOk = 0;
        $ibsModels = [];
        $variantModelsOk = 0;
        $optionImagesOk = 0;
        $variantRows = 0;
        $parentIds = [];

        foreach ($products as $product) {
            if ((int) ($product['from_warehouse'] ?? 0) === 1) {
                $warehouseOk++;
            }

            if (filled($product['ibs_model'] ?? null)) {
                $ibsModels[$product['ibs_model']] = true;
            }

            $parentId = (string) ($product['product_id'] ?? $product['source_product_id'] ?? '');

            if ($parentId !== '') {
                $parentIds[$parentId] = ($parentIds[$parentId] ?? 0) + 1;
            }

            if (($product['type'] ?? '') === 'variable') {
                $variableCount++;
            }

            foreach ($product['options'] ?? [] as $option) {
                $variantRows++;

                if (($option['model'] ?? '—') !== '—') {
                    $variantModelsOk++;
                }

                if (filled($option['image'] ?? null)) {
                    $optionImagesOk++;
                }
            }
        }

        $duplicateParents = count(array_filter($parentIds, fn (int $count) => $count > 1));

        return [
            'api_returned' => count($rawProducts),
            'warehouse_preview' => count($products),
            'warehouse_only' => count($rawProducts) === 0 || $warehouseOk === count($products),
            'all_from_warehouse' => $warehouseOk === count($products),
            'unique_ibs_models' => count($ibsModels),
            'unique_parent_ids' => count($parentIds),
            'duplicate_parents' => $duplicateParents,
            'variable_products' => $variableCount,
            'option_images_count' => $optionImagesOk,
            'variant_models_count' => $variantModelsOk,
            'variant_rows' => $variantRows,
            'products_with_option_images' => $optionImagesOk,
            'variant_ibs_models_present' => $variantModelsOk,
            'health_ok' => count(array_filter($products, fn ($p) => ($p['health']['status'] ?? '') === 'ok')),
        ];
    }
}
