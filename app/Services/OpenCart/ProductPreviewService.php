<?php

namespace App\Services\OpenCart;

use App\Models\Connection;
use RuntimeException;

class ProductPreviewService
{
    public const DEFAULT_LOW_WARNING = 5;

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
        $products = $this->applyHealthRules($products);

        $summary = $this->buildSummary($collection['raw_seen'], $products);

        return [
            'products' => $products,
            'activity' => [],
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
                    'rate' => 'IBS supplier cost (local)',
                    'supplier_cost' => 'Alias of rate for supplier payable reports',
                    'low_warning' => 'Low stock threshold (local)',
                ],
                'local_defaults' => [
                    'low_warning' => self::DEFAULT_LOW_WARNING,
                    'rate' => null,
                    'ibs_stock' => null,
                    'sm_model' => '',
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

        $model = $this->resolveLkModel($product);

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
        $lkModel = $this->resolveLkModel($product);
        $ibsModel = $this->extractIbsModel($product);
        $lowWarning = self::DEFAULT_LOW_WARNING;
        $normalizedOptions = array_map(
            fn (array $option) => $this->normalizeOption($option, $imageContext, $lowWarning),
            $options
        );
        $type = $this->resolveProductType($product);
        $parentStock = $this->resolveParentStock($product);
        $parentImagePath = OpenCartOptionImageResolver::extractProductPath($product);
        $parentImage = $this->mediaUrlResolver->resolveProductImage($product, $imageContext);

        return [
            'product_id' => (string) ($product['product_id'] ?? $product['source_product_id'] ?? ''),
            'oc_product_id' => (string) ($product['product_id'] ?? $product['source_product_id'] ?? ''),
            'source_product_id' => (string) ($product['source_product_id'] ?? $product['product_id'] ?? ''),
            'image' => $parentImage,
            'image_path' => $parentImagePath,
            'lk_model' => $lkModel,
            'model' => $lkModel,
            'ibs_model' => $ibsModel,
            'sm_model' => '',
            'rate' => null,
            'supplier_cost' => null,
            'low_warning' => $lowWarning,
            'stock' => $parentStock,
            'ibs_stock' => null,
            'name' => (string) ($product['name'] ?? ''),
            'type' => $type,
            'from_warehouse' => (int) ($product['from_warehouse'] ?? 0),
            'options' => $normalizedOptions,
            'variants' => $normalizedOptions,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public function applyHealthRules(array $products): array
    {
        $duplicateIbsModels = $this->findDuplicateIbsModels($products);

        return array_map(function (array $product) use ($duplicateIbsModels) {
            $lowWarning = (int) ($product['low_warning'] ?? self::DEFAULT_LOW_WARNING);
            $options = is_array($product['options'] ?? null) ? $product['options'] : [];
            $normalizedOptions = array_map(
                fn (array $option) => $this->applyOptionHealth($option, $lowWarning, $duplicateIbsModels),
                $options
            );

            return array_merge($product, [
                'options' => $normalizedOptions,
                'variants' => $normalizedOptions,
                'supplier_cost' => array_key_exists('rate', $product) && $product['rate'] !== null
                    ? (float) $product['rate']
                    : null,
                'health' => $this->assessParentHealth($product, $normalizedOptions, $lowWarning, $duplicateIbsModels),
            ]);
        }, $products);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, string>
     */
    protected function findDuplicateIbsModels(array $products): array
    {
        $counts = [];

        foreach ($products as $product) {
            $this->tallyIbsModel($counts, (string) ($product['ibs_model'] ?? ''));

            foreach ($product['options'] ?? [] as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $this->tallyIbsModel($counts, (string) ($option['ibs_model'] ?? ''));
            }
        }

        return array_keys(array_filter($counts, fn (int $count) => $count > 1));
    }

    /**
     * @param  array<string, int>  $counts
     */
    protected function tallyIbsModel(array &$counts, string $ibsModel): void
    {
        $ibsModel = trim($ibsModel);

        if ($ibsModel === '') {
            return;
        }

        $counts[$ibsModel] = ($counts[$ibsModel] ?? 0) + 1;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveLkModel(array $product): string
    {
        return trim((string) ($product['model'] ?? ''));
    }

    /**
     * IBS model from API fields only — no LK fallback.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractIbsModel(array $payload): string
    {
        foreach (['ibs_model', 'supplier_model', 'master_model'] as $field) {
            $value = trim((string) ($payload[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
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
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    protected function normalizeOption(array $option, OpenCartImageContext $imageContext, int $lowWarning): array
    {
        $name = trim((string) ($option['option_name'] ?? ''));
        $value = trim((string) ($option['option_value'] ?? ''));
        $lkModel = trim((string) ($option['model'] ?? $option['option_model'] ?? ''));
        $ibsModel = $this->extractIbsModel($option);
        $stock = (int) ($option['quantity'] ?? $option['stock'] ?? $option['option_stock'] ?? 0);
        $imagePath = OpenCartOptionImageResolver::extractFromPayload($option);
        $image = $this->mediaUrlResolver->resolveOptionImage($option, $imageContext);
        $displayModel = $lkModel !== '' ? $lkModel : '—';

        return [
            'option_name' => $name !== '' ? $name : '—',
            'option_value' => $value !== '' ? $value : '—',
            'lk_model' => $displayModel,
            'model' => $displayModel,
            'variant_key' => $displayModel !== '—' ? $displayModel : '',
            'ibs_model' => $ibsModel,
            'sm_model' => '',
            'rate' => null,
            'supplier_cost' => null,
            'low_warning' => null,
            'quantity' => $stock,
            'stock' => $stock,
            'ibs_stock' => null,
            'image' => $image,
            'image_path' => $imagePath,
        ];
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<int, string>  $duplicateIbsModels
     * @return array<string, mixed>
     */
    protected function applyOptionHealth(array $option, int $lowWarning, array $duplicateIbsModels): array
    {
        $ibsModel = (string) ($option['ibs_model'] ?? '');
        $stock = (int) ($option['stock'] ?? 0);
        $image = $option['image'] ?? null;
        $isDuplicate = $ibsModel !== '' && in_array($ibsModel, $duplicateIbsModels, true);
        $optionLow = $this->optionLowWarning($option, $lowWarning);
        $ibsStock = array_key_exists('ibs_stock', $option) && $option['ibs_stock'] !== null
            ? (int) $option['ibs_stock']
            : null;
        $rate = array_key_exists('rate', $option) && $option['rate'] !== null
            ? (float) $option['rate']
            : null;

        $localHealth = $this->assessLocalHealth($rate, $ibsStock, $optionLow, false);
        $ocHealth = $this->buildOcHealth(
            stock: $stock,
            image: is_string($image) ? $image : null,
            ibsModel: $ibsModel,
            isOption: true,
            isDuplicateIbs: $isDuplicate,
        );

        return array_merge($option, [
            'health' => $this->mergeHealthPriority($localHealth, $ocHealth),
        ]);
    }

    public function optionLowWarning(array $option, int $parentLowWarning): int
    {
        if (array_key_exists('low_warning', $option) && $option['low_warning'] !== null) {
            return (int) $option['low_warning'];
        }

        return $parentLowWarning;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int, array<string, mixed>>  $options
     * @param  array<int, string>  $duplicateIbsModels
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function assessParentHealth(
        array $product,
        array $options,
        int $lowWarning,
        array $duplicateIbsModels,
    ): array {
        $ibsModel = (string) ($product['ibs_model'] ?? '');
        $stock = (int) ($product['stock'] ?? 0);
        $image = $product['image'] ?? null;
        $isDuplicate = $ibsModel !== '' && in_array($ibsModel, $duplicateIbsModels, true);
        $isVariable = count($options) > 0;
        $rate = array_key_exists('rate', $product) && $product['rate'] !== null
            ? (float) $product['rate']
            : null;
        $ibsStock = array_key_exists('ibs_stock', $product) && $product['ibs_stock'] !== null
            ? (int) $product['ibs_stock']
            : null;

        $healths = [];

        if ($isVariable) {
            if ($rate === null) {
                $healths[] = $this->healthResult('critical', 'Critical', ['Missing Rate']);
            }

            foreach ($options as $option) {
                $optionLow = $this->optionLowWarning($option, $lowWarning);
                $variantIbsStock = array_key_exists('ibs_stock', $option) && $option['ibs_stock'] !== null
                    ? (int) $option['ibs_stock']
                    : null;
                $healths[] = $this->assessLocalHealth(null, $variantIbsStock, $optionLow, false);
            }
        } else {
            $healths[] = $this->assessLocalHealth($rate, $ibsStock, $lowWarning, true);
        }

        $healths[] = $this->buildOcHealth(
            stock: $isVariable ? $this->resolveVariableParentStock($options, $stock) : $stock,
            image: is_string($image) ? $image : null,
            ibsModel: $ibsModel,
            isOption: false,
            isDuplicateIbs: $isDuplicate,
        );

        if ($isVariable) {
            foreach ($options as $option) {
                $healths[] = $this->buildOcHealth(
                    stock: (int) ($option['stock'] ?? 0),
                    image: is_string($option['image'] ?? null) ? $option['image'] : null,
                    ibsModel: (string) ($option['ibs_model'] ?? ''),
                    isOption: true,
                    isDuplicateIbs: in_array((string) ($option['ibs_model'] ?? ''), $duplicateIbsModels, true),
                );
            }
        }

        return $this->mergeHealthPriority(...$healths);
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    protected function resolveVariableParentStock(array $options, int $parentStock): int
    {
        if ($options === []) {
            return $parentStock;
        }

        $stocks = array_map(fn (array $option) => (int) ($option['stock'] ?? 0), $options);

        return min($stocks);
    }

    /**
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function assessLocalHealth(
        ?float $rate,
        ?int $ibsStock,
        int $lowWarning,
        bool $checkRate,
    ): array {
        if ($checkRate && $rate === null) {
            return $this->healthResult('critical', 'Critical', ['Missing Rate']);
        }

        if ($ibsStock === null) {
            return $this->healthResult('warning', 'Warning', ['Missing IBS Stock']);
        }

        if ($ibsStock < $lowWarning) {
            return $this->healthResult('alert', 'Alert', ['Low stock']);
        }

        return $this->healthResult('ok', 'OK', []);
    }

    /**
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function buildOcHealth(
        int $stock,
        ?string $image,
        string $ibsModel,
        bool $isOption,
        bool $isDuplicateIbs,
    ): array {
        $issues = [];

        if ($stock < 0) {
            $issues[] = 'Negative stock';
        }

        if ($ibsModel === '') {
            $issues[] = 'Missing IBS model';
        }

        if (blank($image)) {
            $issues[] = $isOption ? 'Missing option image' : 'Missing main image';
        }

        if ($isDuplicateIbs) {
            $issues[] = 'Duplicate IBS model';
        }

        if ($issues !== []) {
            return $this->healthResult('needs_attention', 'Review', array_values(array_unique($issues)));
        }

        return $this->healthResult('ok', 'OK', []);
    }

    /** @var array<string, int> */
    protected const HEALTH_PRIORITY = [
        'critical' => 5,
        'warning' => 4,
        'alert' => 3,
        'low' => 3,
        'needs_attention' => 2,
        'ok' => 1,
    ];

    /**
     * @param  array{status: string, label: string, issues: array<int, string>}  ...$healths
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function mergeHealthPriority(array ...$healths): array
    {
        $winner = $this->healthResult('ok', 'OK', []);
        $winnerPriority = 1;
        $allIssues = [];

        foreach ($healths as $health) {
            $status = $health['status'] ?? 'ok';
            $priority = self::HEALTH_PRIORITY[$status] ?? 1;

            if ($priority > $winnerPriority) {
                $winnerPriority = $priority;
                $winner = $health;
            }

            foreach ($health['issues'] ?? [] as $issue) {
                $allIssues[] = $issue;
            }
        }

        if ($winnerPriority <= 1) {
            return $this->healthResult('ok', 'OK', []);
        }

        $status = $winner['status'] ?? 'ok';
        $label = $winner['label'] ?? 'OK';

        if ($status === 'low') {
            $status = 'alert';
            $label = 'Alert';
        }

        return [
            'status' => $status,
            'label' => $label,
            'issues' => array_values(array_unique($allIssues)),
        ];
    }

    /**
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function buildHealth(
        int $stock,
        ?string $image,
        string $ibsModel,
        int $lowWarning,
        bool $isOption,
        bool $isDuplicateIbs,
        bool $skipLowStock = false,
    ): array {
        $reviewIssues = [];
        $lowIssues = [];

        if ($stock < 0) {
            $reviewIssues[] = 'Negative stock';
        } elseif (! $skipLowStock && $stock < $lowWarning) {
            $lowIssues[] = 'Stock below low warning';
        }

        if ($ibsModel === '') {
            $reviewIssues[] = 'Missing IBS model';
        }

        if (blank($image)) {
            $reviewIssues[] = $isOption ? 'Missing option image' : 'Missing main image';
        }

        if ($isDuplicateIbs) {
            $reviewIssues[] = 'Duplicate IBS model';
        }

        if ($reviewIssues !== []) {
            return $this->healthResult('needs_attention', 'Review', array_values(array_unique($reviewIssues)));
        }

        if ($lowIssues !== []) {
            return $this->healthResult('alert', 'Alert', $lowIssues);
        }

        return $this->healthResult('ok', 'OK', []);
    }

    /**
     * @param  array{status: string, label: string, issues: array<int, string>}  $base
     * @param  array{status: string, label: string, issues: array<int, string>}  $rollup
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function mergeHealth(array $base, array $rollup): array
    {
        return $this->mergeHealthPriority($base, $rollup);
    }

    /**
     * @param  array<int, string>  $issues
     * @return array{status: string, label: string, issues: array<int, string>}
     */
    protected function healthResult(string $status, string $label, array $issues): array
    {
        return [
            'status' => $status,
            'label' => $label,
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawProducts
     * @param  array<int, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public function buildSummary(array $rawProducts, array $products): array
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

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    public function refreshPreviewState(array $preview): array
    {
        $products = $this->applyHealthRules(is_array($preview['products'] ?? null) ? $preview['products'] : []);
        $apiReturned = (int) ($preview['summary']['api_returned'] ?? count($products));

        $preview['products'] = $products;
        $preview['summary'] = $this->buildSummary(array_fill(0, max(0, $apiReturned), []), $products);

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $fresh
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function mergeLocalPreview(array $fresh, array $existing): array
    {
        $existingProducts = is_array($existing['products'] ?? null) ? $existing['products'] : [];

        if ($existingProducts === []) {
            return $fresh;
        }

        $existingById = [];

        foreach ($existingProducts as $product) {
            if (! is_array($product)) {
                continue;
            }

            $id = (string) ($product['product_id'] ?? $product['source_product_id'] ?? $product['oc_product_id'] ?? '');

            if ($id !== '') {
                $existingById[$id] = $product;
            }
        }

        $localFields = ['rate', 'ibs_stock', 'ibs_model', 'sm_model', 'low_warning'];
        $freshProducts = is_array($fresh['products'] ?? null) ? $fresh['products'] : [];

        foreach ($freshProducts as $index => $product) {
            if (! is_array($product)) {
                continue;
            }

            $id = (string) ($product['product_id'] ?? $product['source_product_id'] ?? $product['oc_product_id'] ?? '');

            if ($id === '' || ! isset($existingById[$id])) {
                continue;
            }

            $old = $existingById[$id];

            foreach ($localFields as $field) {
                if (array_key_exists($field, $old)) {
                    $product[$field] = $old[$field];
                }
            }

            $oldOptions = is_array($old['options'] ?? null) ? $old['options'] : [];
            $options = is_array($product['options'] ?? null) ? $product['options'] : [];

            foreach ($options as $variantIndex => $option) {
                if (! is_array($option) || ! isset($oldOptions[$variantIndex]) || ! is_array($oldOptions[$variantIndex])) {
                    continue;
                }

                foreach ($localFields as $field) {
                    if (array_key_exists($field, $oldOptions[$variantIndex])) {
                        $option[$field] = $oldOptions[$variantIndex][$field];
                    }
                }

                $options[$variantIndex] = $option;
            }

            $product['options'] = $options;
            $product['variants'] = $options;
            $freshProducts[$index] = $product;
        }

        $fresh['products'] = $freshProducts;
        $fresh['activity'] = is_array($existing['activity'] ?? null) ? $existing['activity'] : [];
        $fresh['meta'] = is_array($fresh['meta'] ?? null) ? $fresh['meta'] : [];
        $fresh['meta']['has_local_edits'] = (bool) ($existing['meta']['has_local_edits'] ?? false);

        return $this->refreshPreviewState($fresh);
    }
}
