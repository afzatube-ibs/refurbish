<?php

namespace App\Services;

use App\Models\Connection;
use App\Services\OpenCart\ConnectionService;
use App\Services\OrderMap\OrderMapLoadLogService;
use App\Services\ProductMap\ProductMapCatalogService;
use App\Services\ProductMap\ProductMapLogsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogsDiagnosticsService
{
    public function __construct(
        private readonly ConnectionService $connectionService,
        private readonly ProductMapLogsService $productMapLogsService,
        private readonly OrderMapLoadLogService $orderMapLoadLogService,
        private readonly ProductMapCatalogService $productMapCatalogService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $defaultTab = $this->resolveDefaultTab($request);

        return [
            'defaultTab' => $defaultTab,
            'openOnLoad' => $request->boolean('logs') || session()->has('logs_tab'),
            'currentPage' => $this->currentPageTab($request),
            'connection' => $this->connectionTab(),
            'productMap' => $this->productMapTab(),
            'system' => $this->systemTab(),
        ];
    }

    protected function resolveDefaultTab(Request $request): string
    {
        $tab = session('logs_tab') ?? $request->query('logs_tab', '');

        if (in_array($tab, ['current', 'connection', 'product-map', 'system'], true)) {
            return $tab;
        }

        if ($request->routeIs('connection.*')) {
            return 'connection';
        }

        if ($request->routeIs('product-map.*')) {
            return 'product-map';
        }

        if ($request->routeIs('order-map.*')) {
            return 'current';
        }

        return 'current';
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentPageTab(Request $request): array
    {
        $route = $request->route();
        $routeName = $route?->getName() ?? 'unknown';
        $pageLabel = $this->pageLabelForRoute($routeName);

        $lastError = session('error');
        if (! $lastError && $request->session()->get('errors')?->any()) {
            $lastError = $request->session()->get('errors')->first();
        }

        $flashSuccess = session('success');
        $flashInfo = session('info');

        $status = 'neutral';
        $statusLabel = 'No issues reported';

        if ($lastError) {
            $status = 'error';
            $statusLabel = 'Error on last action';
        } elseif ($flashSuccess) {
            $status = 'ok';
            $statusLabel = 'Last action succeeded';
        } elseif ($flashInfo) {
            $status = 'neutral';
            $statusLabel = 'Informational';
        }

        $summary = [
            'Page' => $pageLabel,
            'Route' => $routeName,
            'Method' => $request->method(),
            'User' => $request->user()?->name ?? '—',
        ];

        if ($flashSuccess) {
            $summary['Success'] = $flashSuccess;
        }

        if ($flashInfo) {
            $summary['Info'] = $flashInfo;
        }

        if ($request->session()->get('errors')?->any()) {
            $summary['Validation errors'] = $request->session()->get('errors')->all();
        }

        $context = $this->pageContext($request, $routeName);
        if ($context !== []) {
            $summary['Context'] = $context;
        }

        return $this->tabPayload(
            status: $status,
            statusLabel: $statusLabel,
            lastError: is_string($lastError) ? $lastError : null,
            lastTestTime: null,
            summary: $summary,
            hasLogs: filled($lastError) || filled($flashSuccess) || filled($flashInfo) || $request->session()->get('errors')?->any(),
            clearRoute: null,
            advanced: [
                'route' => $routeName,
                'uri' => $request->path(),
                'query' => $request->query(),
                'flash' => [
                    'success' => $flashSuccess,
                    'error' => session('error'),
                    'info' => $flashInfo,
                ],
                'validation_errors' => $request->session()->get('errors')?->all() ?? [],
                'context' => $context,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function connectionTab(): array
    {
        $connection = $this->connectionService->getActive();
        $testPayload = session('test_results');
        $checks = is_array($testPayload) ? ($testPayload['checks'] ?? $testPayload) : null;
        $hasSessionLogs = is_array($testPayload);

        $lastTestAt = $connection->last_connection_test_at;
        $lastStatus = $connection->last_connection_test_status;

        $status = match ($lastStatus) {
            'passed' => 'ok',
            'failed' => 'error',
            default => $hasSessionLogs ? 'warning' : 'neutral',
        };

        $statusLabel = match ($lastStatus) {
            'passed' => 'Last saved test passed',
            'failed' => 'Last saved test failed',
            default => $hasSessionLogs ? 'Session test results available' : 'No connection test recorded',
        };

        $lastError = null;
        if (is_array($checks)) {
            foreach ($checks as $check) {
                if (! is_array($check) || ($check['optional'] ?? false)) {
                    continue;
                }
                if (! ($check['passed'] ?? false)) {
                    $lastError = $check['message'] ?? $check['label'] ?? 'A required check failed';

                    break;
                }
            }
        }

        if (! $lastError && $lastStatus === 'failed') {
            $lastError = $connection->last_connection_test_message;
        }

        $summary = [
            'Store URL' => $connection->store_url ?: '—',
            'Connection active' => $connection->is_active ? 'Yes' : 'No',
            'Last test status' => $lastStatus ? ucfirst($lastStatus) : '—',
            'Last message' => $connection->last_connection_test_message ?: '—',
            'Connector version' => $connection->last_connector_version ?: '—',
            'Option image support' => $connection->last_option_image_summary ?: '—',
        ];

        if (is_array($checks)) {
            $passed = collect($checks)->filter(fn ($c) => is_array($c) && ($c['passed'] ?? false))->count();
            $summary['Session checks'] = $passed.'/'.count($checks).' passed';
        }

        $advanced = $this->stripSecrets([
            'db_snapshot' => [
                'last_connection_test_at' => $lastTestAt?->toIso8601String(),
                'last_connection_test_status' => $lastStatus,
                'last_connection_test_message' => $connection->last_connection_test_message,
                'last_connector_version' => $connection->last_connector_version,
                'last_option_image_summary' => $connection->last_option_image_summary,
            ],
            'session' => $hasSessionLogs ? [
                'meta' => $testPayload['meta'] ?? null,
                'checks' => $checks,
                'diagnostics' => $testPayload['diagnostics'] ?? null,
                'sample' => $testPayload['sample'] ?? null,
            ] : null,
        ]);

        return $this->tabPayload(
            status: $status,
            statusLabel: $statusLabel,
            lastError: $lastError,
            lastTestTime: $lastTestAt?->format('M j, Y g:i A'),
            summary: $summary,
            hasLogs: $hasSessionLogs,
            clearRoute: 'connection.clear-logs',
            advanced: $advanced,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function productMapTab(): array
    {
        $hasPreview = false;
        $preview = null;

        try {
            $hasPreview = $this->productMapCatalogService->hasProductsSafely();
            $preview = $hasPreview ? $this->productMapCatalogService->buildPreview() : null;
        } catch (\RuntimeException) {
            // Supplier not configured yet — leave Product Map diagnostics empty.
        }

        $meta = is_array($preview) ? ($preview['meta'] ?? []) : [];
        $summaryData = is_array($preview) ? ($preview['summary'] ?? []) : [];
        $syncContext = session(\App\Http\Controllers\ProductMapController::SYNC_CONTEXT_SESSION_KEY);
        $diagnostics = is_array($syncContext['diagnostics'] ?? null) ? $syncContext['diagnostics'] : [];
        $activity = is_array($preview) ? ($preview['activity'] ?? []) : [];
        $sessionLogs = $this->productMapLogsService->all();

        $loadedAt = $meta['loaded_at'] ?? ($sessionLogs['last_action_at'] ?? null);
        $healthOk = $summaryData['health_ok'] ?? null;

        $status = 'neutral';
        $statusLabel = 'No products in database';

        if ($hasPreview) {
            $status = $healthOk === false ? 'warning' : 'ok';
            $statusLabel = $healthOk === false ? 'Database catalog loaded — review health flags' : 'Database catalog loaded';
        } elseif ($this->productMapLogsService->hasClearableLogs()) {
            $status = 'neutral';
            $statusLabel = 'Diagnostic logs available';
        }

        $lastError = $this->productMapLogsService->lastError();

        if ($lastError && ! $hasPreview) {
            $status = 'error';
            $statusLabel = 'Last Product Map action failed';
        } elseif ($lastError && $hasPreview) {
            $status = 'warning';
            $statusLabel = 'Preview loaded — last action reported an error';
        }

        $summary = [
            'App version' => (string) config('dropflow.version', 'v0.7.0'),
            'Catalog source' => $hasPreview ? 'DropFlow database' : '—',
            'Warehouse products' => $hasPreview ? (string) ($meta['warehouse_count'] ?? count($preview['products'] ?? [])) : '—',
            'Last product sync' => $hasPreview && filled($meta['last_product_sync_at'] ?? null)
                ? $this->formatIsoTime((string) $meta['last_product_sync_at'])
                : '—',
            'Last LK fetch' => $hasPreview && filled($meta['last_lk_fetch']['at'] ?? null)
                ? $this->formatIsoTime((string) $meta['last_lk_fetch']['at'])
                : '—',
            'Unique IBS models' => $hasPreview ? (string) ($summaryData['unique_ibs_models'] ?? '—') : '—',
            'Health OK' => $hasPreview ? ($healthOk ? 'Yes' : 'Needs review') : '—',
            'Option images' => $hasPreview ? (string) ($summaryData['option_images_count'] ?? '—') : '—',
            'Incremental sync' => config('dropflow.product_sync_supports_changed_since') ? 'changed_since enabled' : 'full sync only',
        ];

        if (($sessionLogs['last_action'] ?? null) !== null) {
            $summary['Last action'] = (string) $sessionLogs['last_action'];
        }

        $advanced = $this->stripSecrets([
            'mapping_structure' => [
                'chain' => 'IBS Model (master key) → LK Model (active) → SM Model (reserved)',
                'master' => 'IBS Model',
                'active' => 'LK Model — current live source',
                'reserved' => 'SM Model',
                'live_source' => 'LK / Lokkisona',
                'technical_source' => 'OpenCart connector (IBS)',
                'scope' => 'Warehouse products only',
                'mode' => 'Local DB-first catalog — LK sync on demand',
            ],
            'session_logs' => $sessionLogs !== [] ? $sessionLogs : null,
            'meta' => $meta,
            'summary' => $summaryData,
            'diagnostics' => $diagnostics,
            'activity' => $activity,
        ]);

        return $this->tabPayload(
            status: $status,
            statusLabel: $statusLabel,
            lastError: $lastError,
            lastTestTime: $loadedAt ? $this->formatIsoTime($loadedAt) : null,
            summary: $summary,
            hasLogs: $this->productMapLogsService->hasClearableLogs(),
            clearRoute: 'product-map.clear-logs',
            resetRoute: 'product-map.reset',
            advanced: $advanced,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function systemTab(): array
    {
        $modules = config('dropflow.modules', []);
        $logTail = $this->readLogTail();
        $lastLogError = $this->lastErrorFromLogTail($logTail);

        $status = config('app.debug') ? 'warning' : 'ok';
        $statusLabel = config('app.debug') ? 'Debug mode enabled' : 'Production mode';

        if ($lastLogError) {
            $status = 'error';
            $statusLabel = 'Recent error in application log';
        }

        $summary = [
            'Environment' => config('app.env'),
            'App version' => (string) config('dropflow.version', 'v0.7.0'),
            'Debug mode' => config('app.debug') ? 'On' : 'Off',
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Live read-only' => config('dropflow.live_read_only') ? 'Yes' : 'No',
            'LK connector mock' => config('dropflow.oc_mock') ? 'Yes' : 'No',
            'Modules' => collect($modules)
                ->map(fn ($enabled, $key) => $key.': '.($enabled ? 'on' : 'off'))
                ->implode(', '),
        ];

        if ($lastLogError) {
            $summary['Last log error'] = $lastLogError;
        }

        $advanced = [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
            ],
            'dropflow' => [
                'live_read_only' => config('dropflow.live_read_only'),
                'oc_mock' => config('dropflow.oc_mock'),
                'allow_opencart_sync' => config('dropflow.allow_opencart_sync'),
                'modules' => $modules,
                'product_preview_page_size' => config('dropflow.product_preview_page_size'),
                'connection_test_timeout' => config('dropflow.connection_test_timeout'),
            ],
            'runtime' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'timezone' => config('app.timezone'),
            ],
            'log_tail' => $logTail,
        ];

        return $this->tabPayload(
            status: $status,
            statusLabel: $statusLabel,
            lastError: $lastLogError,
            lastTestTime: null,
            summary: $summary,
            hasLogs: $logTail !== [],
            clearRoute: null,
            advanced: $advanced,
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function tabPayload(
        string $status,
        string $statusLabel,
        ?string $lastError,
        ?string $lastTestTime,
        array $summary,
        bool $hasLogs,
        ?string $clearRoute,
        mixed $advanced,
        ?string $resetRoute = null,
    ): array {
        return [
            'status' => $status,
            'status_label' => $statusLabel,
            'last_error' => $lastError,
            'last_test_time' => $lastTestTime,
            'summary' => $summary,
            'has_logs' => $hasLogs,
            'clear_route' => $clearRoute,
            'reset_route' => $resetRoute,
            'advanced' => $advanced,
            'copy_json' => json_encode($this->stripSecrets([
                'status' => $status,
                'status_label' => $statusLabel,
                'last_error' => $lastError,
                'last_test_time' => $lastTestTime,
                'summary' => $summary,
                'advanced' => $advanced,
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    protected function pageLabelForRoute(string $routeName): string
    {
        return match (true) {
            $routeName === 'dashboard' => 'Dashboard',
            str_starts_with($routeName, 'connection.') => 'Connection',
            str_starts_with($routeName, 'product-map.') => 'Product Map',
            str_starts_with($routeName, 'order-map.') => 'Order Map',
            default => str_replace(['.', '-'], ' ', $routeName),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function pageContext(Request $request, string $routeName): array
    {
        if (str_starts_with($routeName, 'connection.')) {
            $connection = Connection::getInstance();
            $hasSaved = filled($connection->store_url) && filled($connection->api_token);

            return [
                'editing' => (bool) session('connection_editing', false),
                'has_saved_connection' => $hasSaved,
                'badge' => $this->connectionService->resolveBadgeStatus(
                    $connection,
                    $hasSaved,
                    (bool) session('connection_editing', false) || old('store_url') !== null || ! $hasSaved
                ),
            ];
        }

        if (str_starts_with($routeName, 'product-map.')) {
            $connection = $this->connectionService->getActive();
            $hasCatalog = $this->productMapCatalogService->hasProductsSafely();

            return [
                'connection_ready' => $connection->is_active && filled($connection->store_url) && filled($connection->api_token),
                'preview_loaded' => $hasCatalog,
                'product_count' => $hasCatalog ? $this->productMapCatalogService->productCount() : 0,
            ];
        }

        if (str_starts_with($routeName, 'order-map.')) {
            $lastSync = $this->orderMapLoadLogService->last();
            $context = [];

            if ($lastSync !== []) {
                $context['last_sync'] = $this->orderMapLoadLogService->diagnosticsSummary();
                $context['requested_status_ids'] = $lastSync['requested_status_ids'] ?? [];
                $context['connector_orders'] = $lastSync['connector_orders'] ?? [];
                $context['skip_log'] = array_map(
                    fn (array $entry) => $this->orderMapLoadLogService->formatSkipRow($entry),
                    is_array($lastSync['skip_log'] ?? null) ? $lastSync['skip_log'] : []
                );
            }

            return $context;
        }

        return [];
    }

    protected function formatIsoTime(string $iso): string
    {
        try {
            return \Carbon\Carbon::parse($iso)->format('M j, Y g:i A');
        } catch (\Throwable) {
            return $iso;
        }
    }

    /**
     * @return list<string>
     */
    protected function readLogTail(int $lines = 30): array
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return [];
        }

        try {
            $content = File::get($path);
        } catch (\Throwable) {
            return [];
        }

        $allLines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $tail = array_slice($allLines, -$lines);

        return array_values(array_filter($tail, fn ($line) => is_string($line) && $line !== ''));
    }

    /**
     * @param  list<string>  $logTail
     */
    protected function lastErrorFromLogTail(array $logTail): ?string
    {
        for ($i = count($logTail) - 1; $i >= 0; $i--) {
            $line = $logTail[$i];
            if (preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/i', $line)) {
                return mb_substr($line, 0, 240);
            }
        }

        return null;
    }

    /**
     * @param  mixed  $data
     * @return mixed
     */
    protected function stripSecrets(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), ['api_token', 'token', 'password', 'secret', 'authorization'], true)) {
                $result[$key] = '••••••••';

                continue;
            }

            $result[$key] = $this->stripSecrets($value);
        }

        return $result;
    }
}
