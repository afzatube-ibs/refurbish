<?php

namespace App\Services\OpenCart;

use App\Models\Connection;
use RuntimeException;

class ConnectionService
{
    public function getActive(): Connection
    {
        return $this->ensureIbsEndpoints(Connection::getInstance());
    }

    public function save(array $data): Connection
    {
        $connection = Connection::getInstance();

        if (array_key_exists('api_token', $data) && blank($data['api_token'])) {
            unset($data['api_token']);
        }

        $connection->fill(IbsRouteResolver::normalizeConnectionInput($data));
        $connection->save();

        return $connection->fresh();
    }

    public function ensureIbsEndpoints(Connection $connection): Connection
    {
        $normalized = IbsRouteResolver::normalizeConnectionInput([
            'product_api_endpoint' => $connection->product_api_endpoint,
            'order_api_endpoint' => $connection->order_api_endpoint,
            'order_status_api_endpoint' => $connection->order_status_api_endpoint,
        ]);

        $dirty = false;

        foreach ($normalized as $field => $value) {
            if ($connection->{$field} !== $value) {
                $connection->{$field} = $value;
                $dirty = true;
            }
        }

        if ($dirty && $connection->exists) {
            $connection->save();
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveConnectionForTest(array $data): Connection
    {
        $saved = Connection::getInstance();
        $data = IbsRouteResolver::normalizeConnectionInput($data);
        $connection = new Connection;

        $connection->forceFill([
            'store_url' => $data['store_url'],
            'product_api_endpoint' => $data['product_api_endpoint'],
            'order_api_endpoint' => $data['order_api_endpoint'],
            'order_status_api_endpoint' => $data['order_status_api_endpoint'],
            'supplier_filter' => $data['supplier_filter'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if (filled($data['api_token'] ?? null)) {
            $connection->api_token = $data['api_token'];
        } else {
            $connection->api_token = $saved->api_token;
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, uses_existing_token: bool}
     */
    public function fingerprintContext(array $data, Connection $connection): array
    {
        $normalized = IbsRouteResolver::normalizeConnectionInput($data);
        $normalized['store_url'] = rtrim((string) ($normalized['store_url'] ?? ''), '/');
        $normalized['is_active'] = $this->normalizeBooleanInput($normalized['is_active'] ?? false);

        if (filled($normalized['api_token'] ?? null)) {
            return [
                'data' => $normalized,
                'uses_existing_token' => false,
            ];
        }

        unset($normalized['api_token']);

        if (filled($connection->api_token)) {
            return [
                'data' => $normalized,
                'uses_existing_token' => true,
            ];
        }

        $pendingToken = session('connection_pending_api_token');
        if (is_string($pendingToken) && $pendingToken !== '') {
            $normalized['api_token'] = $pendingToken;

            return [
                'data' => $normalized,
                'uses_existing_token' => false,
            ];
        }

        return [
            'data' => $normalized,
            'uses_existing_token' => false,
        ];
    }

    public function fingerprintFor(array $data, Connection $connection): string
    {
        $context = $this->fingerprintContext($data, $connection);

        return $this->configFingerprint($context['data'], $context['uses_existing_token']);
    }

    public function normalizeBooleanInput(mixed $value, bool $default = false): bool
    {
        if (is_array($value)) {
            $value = end($value);
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function isVerifiedForSave(array $data, Connection $connection): bool
    {
        if (! session()->has('connection_verified_fingerprint')) {
            return false;
        }

        return session('connection_verified_fingerprint') === $this->fingerprintFor($data, $connection);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function markVerificationPassed(array $data, Connection $connection): void
    {
        $context = $this->fingerprintContext($data, $connection);

        session([
            'connection_verified_fingerprint' => $this->configFingerprint(
                $context['data'],
                $context['uses_existing_token']
            ),
        ]);

        if ($context['uses_existing_token'] || blank($context['data']['api_token'] ?? null)) {
            session()->forget('connection_pending_api_token');
        } else {
            session(['connection_pending_api_token' => $context['data']['api_token']]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function dataForSave(array $data, Connection $connection): array
    {
        $data = IbsRouteResolver::normalizeConnectionInput($data);
        $data['store_url'] = rtrim((string) ($data['store_url'] ?? ''), '/');
        $data['is_active'] = $this->normalizeBooleanInput($data['is_active'] ?? false);

        if (filled($data['api_token'] ?? null)) {
            return $data;
        }

        unset($data['api_token']);

        if (filled($connection->api_token)) {
            return $data;
        }

        $pendingToken = session('connection_pending_api_token');
        if (is_string($pendingToken) && $pendingToken !== '') {
            $data['api_token'] = $pendingToken;
        }

        return $data;
    }

    public function clearVerification(): void
    {
        session()->forget([
            'connection_verified_fingerprint',
            'connection_pending_api_token',
        ]);
    }

    /**
     * @param  array<string, mixed>  $results
     */
    public function recordTestSnapshot(Connection $connection, array $results, bool $allPassed): Connection
    {
        $diagnostics = is_array($results['diagnostics'] ?? null) ? $results['diagnostics'] : [];
        $connectionTestBody = is_array($diagnostics['connection_test']['body'] ?? null)
            ? $diagnostics['connection_test']['body']
            : [];

        $connection->forceFill([
            'last_connection_test_at' => now(),
            'last_connection_test_status' => $allPassed ? 'passed' : 'failed',
            'last_connection_test_message' => $allPassed
                ? 'All required checks passed.'
                : $this->firstFailedCheckMessage($results),
            'last_connector_version' => filled($connectionTestBody['connector_version'] ?? null)
                ? (string) $connectionTestBody['connector_version']
                : null,
            'last_option_image_summary' => $this->optionImageSummaryFromResults($results, $diagnostics),
        ]);
        $connection->save();

        return $connection->fresh();
    }

    public function resolveBadgeStatus(Connection $connection, bool $hasSavedConnection, bool $isEditing): string
    {
        if (! $hasSavedConnection || ! $connection->is_active) {
            return 'not_connected';
        }

        if ($isEditing || $connection->last_connection_test_status !== 'passed') {
            return 'needs_test';
        }

        return 'connected';
    }

    /**
     * @param  array<string, mixed>  $results
     */
    protected function firstFailedCheckMessage(array $results): string
    {
        $checks = $results['checks'] ?? $results;

        foreach ($checks as $check) {
            if ($check['optional'] ?? false) {
                continue;
            }

            if (! ($check['passed'] ?? false)) {
                $label = $check['label'] ?? $check['name'] ?? 'Check';

                return $label.' failed.';
            }
        }

        return 'Connection test did not pass.';
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>  $diagnostics
     */
    protected function optionImageSummaryFromResults(array $results, array $diagnostics): ?string
    {
        foreach ($results['checks'] ?? [] as $check) {
            if (($check['key'] ?? null) === 'option_images') {
                $message = (string) ($check['message'] ?? '');
                if ($message !== '' && $message !== 'Optional') {
                    return $message === 'Connected'
                        ? $this->formatOptionImageSummary($diagnostics)
                        : $message;
                }
            }
        }

        return $this->formatOptionImageSummary($diagnostics);
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    protected function formatOptionImageSummary(array $diagnostics): ?string
    {
        $debug = is_array($diagnostics['option_image'] ?? null) ? $diagnostics['option_image'] : [];
        $poipDetected = filter_var($debug['poip_detected'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $joinActive = filter_var($debug['join_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sampleImages = (int) ($debug['sample_images_non_empty'] ?? 0);

        if (! $poipDetected && ! $joinActive && $sampleImages === 0) {
            return null;
        }

        $parts = [];
        if ($poipDetected) {
            $parts[] = 'POIP detected';
        }
        if ($joinActive) {
            $parts[] = 'join active';
        }
        if ($sampleImages > 0) {
            $parts[] = $sampleImages.' sample image(s)';
        }

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function configFingerprint(array $data, bool $usesExistingToken): string
    {
        $data = IbsRouteResolver::normalizeConnectionInput($data);

        $payload = [
            'store_url' => rtrim((string) ($data['store_url'] ?? ''), '/'),
            'product_api_endpoint' => (string) ($data['product_api_endpoint'] ?? ''),
            'order_api_endpoint' => (string) ($data['order_api_endpoint'] ?? ''),
            'order_status_api_endpoint' => (string) ($data['order_status_api_endpoint'] ?? ''),
            'supplier_filter' => (string) ($data['supplier_filter'] ?? ''),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'api_token' => $usesExistingToken
                ? '__existing__'
                : (string) ($data['api_token'] ?? ''),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, array<string, mixed>>|array{checks: array<int, array<string, mixed>>}  $results
     */
    public function allChecksPassed(array $results): bool
    {
        $checks = $results['checks'] ?? $results;

        if ($checks === []) {
            return false;
        }

        foreach ($checks as $check) {
            if ($check['optional'] ?? false) {
                continue;
            }

            if (! ($check['passed'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     checks: array<int, array<string, mixed>>,
     *     sample: array<string, mixed>,
     *     meta: array<string, mixed>,
     *     diagnostics: array<string, mixed>
     * }
     */
    public function runTests(Connection $connection): array
    {
        $client = new OpenCartHttpClient($connection);
        $limit = (int) config('dropflow.connection_test_limit', 1);
        $requestsMade = 0;

        $ping = $client->pingStore();
        $requestsMade++;

        $connectionTest = $client->runConnectionTest();
        $requestsMade++;

        $connectionTestBody = is_array($connectionTest['body'] ?? null) ? $connectionTest['body'] : [];
        $ibsRoutes = IbsRouteResolver::routesFromConnectionTest($connectionTestBody);

        $productEndpoint = IbsRouteResolver::toIndexPhpRoute($ibsRoutes['products']);
        $orderEndpoint = IbsRouteResolver::toIndexPhpRoute($ibsRoutes['orders']);
        $statusEndpoint = IbsRouteResolver::toIndexPhpRoute($ibsRoutes['order_queue_statuses']);

        $productRead = $client->readSample($productEndpoint);
        $requestsMade++;

        $orderRead = $client->readSample($orderEndpoint);
        $requestsMade++;

        $statusRead = $client->readSample($statusEndpoint, [], includePagination: false);
        $requestsMade++;

        $productBody = $productRead['body'] ?? [];
        $orderBody = $orderRead['body'] ?? [];
        $statusBody = $statusRead['body'] ?? [];

        $products = is_array($productBody['products'] ?? null) ? $productBody['products'] : [];
        $orders = is_array($orderBody['orders'] ?? null) ? $orderBody['orders'] : [];
        $statuses = $this->extractOrderStatuses($statusBody);

        $sampleProduct = $products[0] ?? null;

        $has401 = in_array(401, [
            $connectionTest['status'],
            $productRead['status'],
            $orderRead['status'],
            $statusRead['status'],
        ], true);

        $connectionTestOk = $connectionTest['success']
            && (($connectionTestBody['success'] ?? true) === true);
        $ordersFilterMode = (string) ($connectionTestBody['orders_filter_mode'] ?? '');
        $ordersFilterModeOk = $ordersFilterMode === '' || $ordersFilterMode === 'queue_status_only';
        $productApiOk = $productRead['success'] && is_array($productBody['products'] ?? null);
        $orderApiOk = $orderRead['success'] && is_array($orderBody['orders'] ?? null);
        $orderFilterApplied = (string) ($orderBody['filter_applied'] ?? '');
        $orderFilterOk = $orderFilterApplied === '' || $orderFilterApplied === 'queue_status_only';
        $statusApiOk = $statusRead['success'] === true
            && (($statusBody['success'] ?? true) === true);
        $supplierFilterOk = SupplierProductFilter::connectionTestPassed($products, $productApiOk);

        $tokenValid = $connectionTestOk && ! $has401 && $ordersFilterModeOk;

        $optionCheck = $this->optionImageCheck($connectionTestBody);

        $checks = [
            $this->buildCheck(
                key: 'store',
                label: 'Store connection',
                passed: (bool) $ping['success'],
                message: $ping['success'] ? 'Connected' : 'Failed',
                detail: $ping['message'] ?? null,
            ),
            $this->buildCheck(
                key: 'token',
                label: 'Token verification',
                passed: $tokenValid,
                message: $tokenValid ? 'Connected' : 'Failed',
                detail: $has401
                    ? 'API token was rejected (401).'
                    : (! $ordersFilterModeOk
                        ? 'Connector orders_filter_mode must be queue_status_only (got: '.$ordersFilterMode.').'
                        : ($tokenValid
                            ? 'Token accepted via IBS connection test.'
                            : 'IBS connection test did not succeed.')),
            ),
            $this->buildCheck(
                key: 'product_api',
                label: 'Product API',
                passed: $productApiOk,
                message: $productApiOk ? 'Connected' : 'Failed',
                detail: $productRead['message'] ?? null,
            ),
            $this->buildCheck(
                key: 'order_api',
                label: 'Order API',
                passed: $orderApiOk && $orderFilterOk,
                message: ($orderApiOk && $orderFilterOk) ? 'Connected' : 'Failed',
                detail: ! $orderFilterOk
                    ? 'Orders API filter_applied must be queue_status_only (got: '.$orderFilterApplied.').'
                    : ($orderRead['message'] ?? null),
            ),
            $this->buildCheck(
                key: 'order_status_api',
                label: 'Order Status API',
                passed: $statusApiOk,
                message: $statusApiOk ? 'Connected' : 'Failed',
                detail: $statusRead['message'] ?? (
                    $statusApiOk
                        ? sprintf(
                            'Order status API responded (%d statuses, selected %d).',
                            count($statuses),
                            (int) ($statusBody['selected_count'] ?? count($statuses))
                        )
                        : null
                ),
            ),
            $this->buildCheck(
                key: 'supplier_filter',
                label: 'Supplier product filter',
                passed: $supplierFilterOk,
                message: $supplierFilterOk ? 'Connected' : 'Failed',
                detail: $supplierFilterOk
                    ? 'Warehouse product sample returned with from_warehouse=1.'
                    : ($productApiOk && $products !== []
                        ? 'Product sample missing from_warehouse=1 flag.'
                        : 'No warehouse products returned in sample.'),
            ),
            $this->buildCheck(
                key: 'option_images',
                label: 'Option image support',
                passed: $optionCheck['passed'],
                optional: ! $optionCheck['passed'],
                message: $optionCheck['ui_message'],
                detail: $optionCheck['detail'],
                status: $optionCheck['passed'] ? 'connected' : 'optional',
            ),
        ];

        if ($has401 && ($productApiOk || $orderApiOk || $statusApiOk)) {
            foreach ($checks as &$check) {
                if (in_array($check['key'], ['product_api', 'order_api', 'order_status_api', 'supplier_filter'], true) && $check['passed']) {
                    $check['message'] = 'Connected';
                    $check['status'] = 'connected';
                }
            }
            unset($check);
        }

        return [
            'checks' => $checks,
            'sample' => [
                'product' => $sampleProduct,
                'product_variants' => $sampleProduct['variants'] ?? [],
                'order' => $orders[0] ?? null,
                'statuses' => array_slice($statuses, 0, 10),
            ],
            'resolved_endpoints' => IbsRouteResolver::normalizeConnectionInput([
                'product_api_endpoint' => $productEndpoint,
                'order_api_endpoint' => $orderEndpoint,
                'order_status_api_endpoint' => $statusEndpoint,
            ]),
            'meta' => [
                'requests_made' => $requestsMade,
                'http_methods' => ['HEAD/GET', 'GET', 'GET', 'GET', 'GET'],
                'limit_per_sample' => $limit,
                'timeout_seconds' => (int) config('dropflow.connection_test_timeout', 8),
                'auth' => 'api_token query parameter',
                'resolved_endpoints' => [
                    'connection_test' => IbsRouteResolver::toIndexPhpRoute($ibsRoutes['connection_test']),
                    'products' => $productEndpoint,
                    'orders' => $orderEndpoint,
                    'order_statuses' => $statusEndpoint,
                ],
            ],
            'diagnostics' => [
                'ping' => $ping,
                'connection_test' => $this->sanitizeReadForDiagnostics($connectionTest),
                'ibs_routes' => $ibsRoutes,
                'product_read' => $this->sanitizeReadForDiagnostics($productRead),
                'order_read' => $this->sanitizeReadForDiagnostics($orderRead),
                'status_read' => $this->sanitizeReadForDiagnostics($statusRead),
                'supplier_filter' => [
                    'filter' => $connection->supplier_filter,
                    'products_in_sample' => count($products),
                    'from_warehouse_in_sample' => (int) (($products[0]['from_warehouse'] ?? 0)),
                ],
                'option_image' => $optionCheck['debug'],
                'token_has_401' => $has401,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCheck(
        string $key,
        string $label,
        bool $passed,
        string $message,
        ?string $detail = null,
        bool $optional = false,
        ?string $status = null,
    ): array {
        if ($status === null) {
            if ($optional) {
                $status = 'optional';
            } elseif ($passed) {
                $status = 'connected';
            } elseif ($message === 'Needs attention') {
                $status = 'needs_attention';
            } else {
                $status = 'failed';
            }
        }

        return [
            'key' => $key,
            'label' => $label,
            'name' => $label,
            'passed' => $passed,
            'optional' => $optional,
            'status' => $status,
            'message' => $message,
            'detail' => $detail,
        ];
    }

    /**
     * @param  array<string, mixed>  $statusBody
     * @return array<int, mixed>
     */
    protected function extractOrderStatuses(array $statusBody): array
    {
        foreach (['statuses', 'order_queue_statuses', 'queue_statuses'] as $key) {
            if (is_array($statusBody[$key] ?? null)) {
                return $statusBody[$key];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $connectionTestBody
     * @return array{ui_message: string, detail: string, passed: bool, debug: array<string, mixed>}
     */
    protected function optionImageCheck(array $connectionTestBody): array
    {
        $poipDetected = filter_var($connectionTestBody['poip_detected'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $joinActive = filter_var($connectionTestBody['join_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sampleImages = (int) ($connectionTestBody['sample_images_non_empty'] ?? 0);

        $detected = $poipDetected && $joinActive && $sampleImages > 0;

        return [
            'passed' => $detected,
            'ui_message' => $detected ? 'Connected' : 'Optional',
            'detail' => $detected
                ? "POIP detected ({$sampleImages} sample option image(s) found)."
                : 'POIP option image probe not confirmed in connection test.',
            'debug' => [
                'poip_detected' => $poipDetected,
                'join_active' => $joinActive,
                'sample_images_non_empty' => $sampleImages,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $read
     * @return array<string, mixed>
     */
    protected function sanitizeReadForDiagnostics(array $read): array
    {
        return [
            'success' => $read['success'] ?? false,
            'status' => $read['status'] ?? 0,
            'message' => $read['message'] ?? '',
            'body' => $read['body'] ?? null,
        ];
    }

    public function assertSyncAllowed(): void
    {
        if (config('dropflow.live_read_only') || ! config('dropflow.allow_opencart_sync')) {
            throw new RuntimeException(
                'LK sync is disabled during live read-only test phase. Connection test only — no import.'
            );
        }
    }
}
