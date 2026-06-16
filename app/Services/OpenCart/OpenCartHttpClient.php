<?php

namespace App\Services\OpenCart;

use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenCartHttpClient
{
    public function __construct(
        protected Connection $connection
    ) {}

    /**
     * Read-only GET request to OpenCart (IBS query-token contract).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $params = [], ?int $timeout = null): array
    {
        $this->assertReadOnlyMode();

        if ($this->usesMock()) {
            return $this->getMockResponse($endpoint, $params);
        }

        $timeout ??= config('dropflow.connection_test_timeout', 8);

        $response = $this->httpClient($timeout)
            ->get($this->requestUrl($endpoint, $params));

        return $this->parseJsonResponse($response, 'GET');
    }

    /**
     * Lightweight store reachability check (HEAD, falls back to GET).
     *
     * @return array{success: bool, status: int, message: string}
     */
    public function pingStore(): array
    {
        $this->assertReadOnlyMode();

        if ($this->usesMock()) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Mock mode: store ping skipped.',
            ];
        }

        $storeUrl = rtrim($this->connection->store_url, '/');

        if ($storeUrl === '') {
            return [
                'success' => false,
                'status' => 0,
                'message' => 'Store URL is not configured.',
            ];
        }

        $timeout = (int) config('dropflow.connection_ping_timeout', 5);

        try {
            $response = Http::timeout($timeout)->head($storeUrl);

            if ($response->status() === 405 || $response->status() === 501) {
                $response = Http::timeout($timeout)->get($storeUrl);
            }

            return [
                'success' => $response->status() > 0 && $response->status() < 500,
                'status' => $response->status(),
                'message' => $response->status() > 0 && $response->status() < 500
                    ? 'Store responded.'
                    : sprintf('Store returned HTTP %d.', $response->status()),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'status' => 0,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * IBS connection test endpoint — validates api_token via query string.
     *
     * @return array{success: bool, status: int, message: string, body: ?array}
     */
    public function runConnectionTest(?string $endpoint = null): array
    {
        $endpoint ??= IbsRouteResolver::toIndexPhpRoute(
            IbsRouteResolver::defaultRoutes()['connection_test']
        );

        return $this->readSample($endpoint, [], includePagination: false);
    }

    /**
     * Single safe connection-test read: enforces limit=1 for paginated endpoints.
     *
     * @return array{success: bool, status: int, message: string, body: ?array}
     */
    public function readSample(string $endpoint, array $params = [], bool $includePagination = true): array
    {
        $this->assertReadOnlyMode();

        if ($includePagination) {
            $params['page'] = $params['page'] ?? 1;
            $params['limit'] = config('dropflow.connection_test_limit', 1);
        }

        if ($this->usesMock()) {
            try {
                $body = $this->getMockResponse($endpoint, $params);

                return [
                    'success' => ($body['success'] ?? false) === true,
                    'status' => 200,
                    'message' => 'Mock sample read OK.',
                    'body' => $body,
                ];
            } catch (\Throwable $exception) {
                return [
                    'success' => false,
                    'status' => 0,
                    'message' => $exception->getMessage(),
                    'body' => null,
                ];
            }
        }

        $timeout = (int) config('dropflow.connection_test_timeout', 8);

        try {
            $response = $this->httpClient($timeout)
                ->get($this->requestUrl($endpoint, $params));

            $body = $response->json();

            return [
                'success' => $response->successful() && is_array($body),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Sample read OK.'
                    : sprintf('Sample read failed with HTTP %d.', $response->status()),
                'body' => is_array($body) ? $body : null,
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'status' => 0,
                'message' => $exception->getMessage(),
                'body' => null,
            ];
        }
    }

    protected function assertReadOnlyMode(): void
    {
        if (config('dropflow.live_read_only') && config('dropflow.oc_mock')) {
            throw new RuntimeException('Mock mode is blocked while live read-only mode is active.');
        }
    }

    protected function usesMock(): bool
    {
        return (bool) config('dropflow.oc_mock', false);
    }

    protected function httpClient(int $timeout): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()->timeout($timeout);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function requestUrl(string $endpoint, array $params = []): string
    {
        $endpoint = IbsRouteResolver::toIndexPhpRoute($endpoint);
        $url = $this->buildUrl($endpoint);
        $params['api_token'] = (string) ($this->connection->api_token ?? '');

        [$base, $queryString] = array_pad(explode('?', $url, 2), 2, '');
        $existing = [];

        if ($queryString !== '') {
            parse_str($queryString, $existing);
        }

        return $base.'?'.http_build_query(array_merge($existing, $params));
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     * @return array<string, mixed>
     */
    protected function parseJsonResponse($response, string $method): array
    {
        if ($method !== 'GET') {
            throw new RuntimeException('OpenCart connector is read-only. Only GET requests are permitted.');
        }

        if ($response->status() === 401) {
            throw new RuntimeException('OpenCart API rejected the token (401).');
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('OpenCart API %s failed with HTTP %d.', $method, $response->status())
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('OpenCart API returned invalid JSON.');
        }

        return $data;
    }

    protected function buildUrl(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        return rtrim($this->connection->store_url, '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function getMockResponse(string $endpoint, array $params = []): array
    {
        $fixture = $this->resolveFixtureFile($endpoint);
        $path = storage_path('app/'.$fixture);

        if (! is_file($path)) {
            if ($fixture === 'inline:connection_test') {
                return [
                    'success' => true,
                    'connector_version' => '1.1.0',
                    'routes' => IbsRouteResolver::defaultRoutes(),
                    'poip_detected' => true,
                    'join_active' => true,
                    'sample_images_non_empty' => 4,
                ];
            }

            throw new RuntimeException("Mock fixture not found: storage/app/{$fixture}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("Mock fixture is invalid JSON: storage/app/{$fixture}");
        }

        if (str_contains($fixture, 'products.json') && isset($params['page'])) {
            return $this->buildMockProductsPage((int) $params['page'], (int) ($params['limit'] ?? 50));
        }

        if (str_contains($fixture, 'products.json') && isset($params['limit']) && isset($data['products']) && is_array($data['products'])) {
            $data['products'] = array_slice($data['products'], 0, (int) $params['limit']);
        }

        if (isset($params['limit']) && isset($data['orders']) && is_array($data['orders'])) {
            $data['orders'] = array_slice($data['orders'], 0, (int) $params['limit']);
        }

        if (isset($data['orders']) && is_array($data['orders']) && isset($params['status_ids'])) {
            $statusIds = array_map('intval', (array) $params['status_ids']);
            if ($statusIds !== []) {
                $data['orders'] = array_values(array_filter(
                    $data['orders'],
                    function (array $order) use ($statusIds): bool {
                        $statusId = (int) ($order['current_oc_status_id'] ?? $order['order_status_id'] ?? 0);

                        return in_array($statusId, $statusIds, true);
                    }
                ));
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMockProductsPage(int $page, int $limit): array
    {
        $target = (int) config('dropflow.product_preview_target', 42);
        $limit = min(20, max(1, $limit));
        $total = $target + 1;
        $offset = ($page - 1) * $limit;
        $products = [];

        for ($index = $offset; $index < $offset + $limit && $index < $total; $index++) {
            $productId = (string) (100 + $index);

            if ($index === 5) {
                $productId = '100';
            }

            $products[] = $this->mockWarehouseProduct($productId, $index);
        }

        $returned = count($products);
        $hasNext = ($offset + $returned) < $total;

        return [
            'success' => true,
            'image_base_url' => config('dropflow.oc_mock_image_base_url', 'https://www.staging.lokkisona.com'),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_next' => $hasNext,
            ],
            'products' => $products,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mockWarehouseProduct(string $productId, int $index): array
    {
        if ($index === 0) {
            return [
                'product_id' => $productId,
                'source_product_id' => $productId,
                'supplier' => 'ex-a',
                'from_warehouse' => 1,
                'image' => 'catalog/Products/toys/E601.jpg',
                'model' => 'E-601-GREEN',
                'ibs_model' => 'IBS-E601',
                'name' => 'E-601-GREEN Warehouse Product',
                'type' => 'simple',
                'price' => 2499.00,
                'stock' => 120,
                'options' => [
                    [
                        'option_name' => 'Color',
                        'option_value' => 'Green 1',
                        'model' => 'E-601-GREEN-1',
                        'price' => 2499.00,
                        'quantity' => 24,
                        'image' => 'catalog/Products/toys/E601-G1.jpg',
                    ],
                    [
                        'option_name' => 'Color',
                        'option_value' => 'Green 2',
                        'model' => 'E-601-GREEN-2',
                        'price' => 2499.00,
                        'quantity' => 24,
                        'image' => 'catalog/Products/toys/E601-G2.jpg',
                    ],
                    [
                        'option_name' => 'Color',
                        'option_value' => 'Green 3',
                        'model' => 'E-601-GREEN-3',
                        'price' => 2499.00,
                        'quantity' => 24,
                        'image' => 'catalog/Products/toys/E601-G3.jpg',
                    ],
                    [
                        'option_name' => 'Color',
                        'option_value' => 'Green 4',
                        'model' => 'E-601-GREEN-4',
                        'price' => 2499.00,
                        'quantity' => 24,
                        'image' => 'catalog/Products/toys/E601-G4.jpg',
                    ],
                    [
                        'option_name' => 'Color',
                        'option_value' => 'Green 5',
                        'model' => 'E-601-GREEN-5',
                        'price' => 2499.00,
                        'quantity' => 24,
                        'image' => 'catalog/Products/toys/E601-G5.jpg',
                    ],
                ],
            ];
        }

        return [
            'product_id' => $productId,
            'source_product_id' => $productId,
            'supplier' => 'ex-a',
            'from_warehouse' => 1,
            'image' => 'catalog/Products/toys/'.$productId.'.jpg',
            'model' => 'EXA-WH-'.$productId,
            'name' => 'Warehouse Product '.$productId,
            'type' => 'simple',
            'price' => 999.00 + ($index % 10) * 50,
            'stock' => 10 + ($index % 20),
            'options' => [],
        ];
    }

    protected function resolveFixtureFile(string $endpoint): string
    {
        $needle = strtolower($endpoint);

        if (str_contains($needle, 'connection_test')) {
            return 'inline:connection_test';
        }

        if (str_contains($needle, 'order_queue_status') || str_contains($needle, 'order_status') || str_contains($needle, 'status')) {
            return 'fixtures/order_statuses.json';
        }

        if (str_contains($needle, 'order')) {
            return 'fixtures/orders.json';
        }

        return 'fixtures/products.json';
    }
}
