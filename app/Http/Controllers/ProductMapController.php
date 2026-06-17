<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductMapControlSaveRequest;
use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductControlCategoryService;
use App\Services\ProductMap\ProductControlMergeService;
use App\Services\ProductMap\ProductMapCatalogService;
use App\Services\ProductMap\ProductMapListingFilter;
use App\Services\ProductMap\ProductMapLocalControlService;
use App\Services\ProductMap\ProductMapLogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductMapController extends Controller
{
    public const SYNC_CONTEXT_SESSION_KEY = 'product_map_sync_context';

    public function __construct(
        private readonly ProductPreviewService $previewService,
        private readonly ConnectionService $connectionService,
        private readonly ProductMapLocalControlService $localControlService,
        private readonly ProductControlMergeService $controlMergeService,
        private readonly ProductControlCategoryService $categoryService,
        private readonly ProductMapListingFilter $listingFilter,
        private readonly ProductMapLogsService $productMapLogsService,
        private readonly ProductMapCatalogService $catalogService,
    ) {}

    public function index(Request $request): View
    {
        $connection = $this->connectionService->getActive();
        $syncContext = $this->syncContext();
        $preview = $this->catalogService->hasProducts()
            ? $this->catalogService->buildPreview(
                is_array($syncContext['meta'] ?? null) ? $syncContext['meta'] : null,
                is_array($syncContext['diagnostics'] ?? null) ? $syncContext['diagnostics'] : null,
            )
            : null;

        $products = is_array($preview) ? ($preview['products'] ?? []) : [];
        $listingFilters = $this->listingFilter->resolveFromRequest($request);
        $storedCategories = $this->categoryService->categoriesForSupplier();
        $filterCategories = $this->listingFilter->categoryOptions($products, $storedCategories);
        $filteredProducts = $this->listingFilter->apply($products, $listingFilters);
        $total = count($filteredProducts);
        $perPage = $listingFilters['per_page'];
        $totalPages = max(1, (int) ceil(max(0, $total) / max(1, $perPage)));
        $page = max(1, min($totalPages, (int) $request->query('page', 1)));
        $offset = ($page - 1) * $perPage;
        $listingProducts = $total > 0
            ? array_slice($filteredProducts, $offset, $perPage, true)
            : [];
        $listingQuery = $this->listingFilter->queryParams($listingFilters);

        return view('product-map.preview', [
            'connection' => $connection,
            'connectionReady' => $connection->is_active && filled($connection->store_url) && filled($connection->api_token),
            'preview' => is_array($preview) ? $preview : null,
            'products' => $products,
            'listingProducts' => $listingProducts,
            'listingFilters' => $listingFilters,
            'listingQuery' => $listingQuery,
            'filterCategories' => $filterCategories,
            'listingPagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
            'previewMeta' => is_array($preview) ? ($preview['meta'] ?? null) : null,
            'previewSummary' => is_array($preview) ? ($preview['summary'] ?? null) : null,
            'previewDiagnostics' => is_array($preview) ? ($preview['diagnostics'] ?? null) : null,
            'productCategories' => $storedCategories,
            'pendingLoad' => session('product_map_pending_load'),
            'pendingProducts' => is_array($pending = session('product_map_pending_load'))
                ? ($pending['products'] ?? [])
                : [],
        ]);
    }

    public function saveControl(ProductMapControlSaveRequest $request): JsonResponse
    {
        if (! $this->catalogService->hasProducts()) {
            return response()->json([
                'success' => false,
                'message' => 'Sync products before saving local control changes.',
            ], 422);
        }

        try {
            $syncContext = $this->syncContext();
            $preview = $this->catalogService->buildPreview(
                is_array($syncContext['meta'] ?? null) ? $syncContext['meta'] : null,
                is_array($syncContext['diagnostics'] ?? null) ? $syncContext['diagnostics'] : null,
            );
            $productIndex = (int) $request->validated('product_index');
            $preview = $this->localControlService->save(
                $preview,
                $productIndex,
                $request->only(['changes']),
                $request->user(),
            );

            $product = $preview['products'][$productIndex] ?? null;
            $productId = (string) ($product['product_id'] ?? $product['oc_product_id'] ?? '');

            return response()->json([
                'success' => true,
                'message' => 'Product control saved.',
                'product_index' => $productIndex,
                'product' => $product,
                'summary' => $preview['summary'] ?? [],
                'history' => $this->localControlService->historyForProduct($productId),
                'history_count' => $this->localControlService->historyCount($productId),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function controlHistory(Request $request): JsonResponse
    {
        $productId = trim((string) $request->query('product_id', ''));

        if ($productId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Product id is required.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'history' => $this->localControlService->historyForProduct($productId),
            'history_count' => $this->localControlService->historyCount($productId),
            'categories' => app(ProductControlCategoryService::class)->categoriesForSupplier(),
        ]);
    }

    public function load(): RedirectResponse
    {
        try {
            $fresh = $this->previewService->loadPreview();
            $freshProducts = is_array($fresh['products'] ?? null) ? $fresh['products'] : [];
            $changes = $this->catalogService->detectSyncChanges($freshProducts);

            if ($changes === []) {
                $message = $this->catalogService->hasProducts()
                    ? 'No new or changed products found.'
                    : 'No products found in OpenCart.';

                return redirect()
                    ->route('product-map.index')
                    ->with('info', $message);
            }

            session()->put('product_map_pending_load', [
                'count' => count($changes),
                'products' => $changes,
                'fetch_meta' => is_array($fresh['meta'] ?? null) ? $fresh['meta'] : [],
                'fetch_diagnostics' => is_array($fresh['diagnostics'] ?? null) ? $fresh['diagnostics'] : [],
            ]);

            $this->productMapLogsService->recordLoadEvent('sync_detect', [
                'change_count' => count($changes),
            ]);

            $newCount = count(array_filter($changes, fn (array $row) => ($row['_sync_status'] ?? '') === 'new'));
            $changedCount = count($changes) - $newCount;
            $message = match (true) {
                $newCount > 0 && $changedCount > 0 => $newCount.' new and '.$changedCount.' changed products found',
                $changedCount > 0 => ($changedCount === 1 ? '1 changed product' : $changedCount.' changed products').' found',
                default => ($newCount === 1 ? '1 new product' : $newCount.' new products').' found',
            };

            return redirect()
                ->route('product-map.index')
                ->with('info', $message);
        } catch (\Throwable $exception) {
            $this->productMapLogsService->recordError($exception->getMessage());

            return redirect()
                ->route('product-map.index')
                ->with('error', $exception->getMessage());
        }
    }

    public function confirmLoad(): RedirectResponse
    {
        $pending = session('product_map_pending_load');

        if (! is_array($pending) || empty($pending['products'])) {
            return redirect()
                ->route('product-map.index')
                ->with('error', 'No pending products to sync. Use Sync OC Products first.');
        }

        try {
            $saved = $this->catalogService->upsertProducts($pending['products']);

            session()->put(self::SYNC_CONTEXT_SESSION_KEY, [
                'meta' => is_array($pending['fetch_meta'] ?? null) ? $pending['fetch_meta'] : [],
                'diagnostics' => is_array($pending['fetch_diagnostics'] ?? null) ? $pending['fetch_diagnostics'] : [],
                'synced_at' => now()->toIso8601String(),
            ]);
            session()->forget('product_map_pending_load');

            $this->productMapLogsService->recordLoadEvent('sync_confirm', [
                'saved_count' => $saved,
            ]);

            $message = $saved === 1
                ? '1 product synced to Product Map.'
                : $saved.' products synced to Product Map.';

            return redirect()
                ->route('product-map.index')
                ->with('success', $message);
        } catch (\Throwable $exception) {
            $this->productMapLogsService->recordError($exception->getMessage());

            return redirect()
                ->route('product-map.index')
                ->with('error', $exception->getMessage());
        }
    }

    public function cancelLoad(): RedirectResponse
    {
        session()->forget('product_map_pending_load');

        return redirect()
            ->route('product-map.index')
            ->with('info', 'OpenCart sync cancelled.');
    }

    public function refresh(): RedirectResponse
    {
        if (! $this->catalogService->hasProducts()) {
            return redirect()
                ->route('product-map.index')
                ->with('error', 'No products in Product Map yet. Use Sync OC Products first.');
        }

        try {
            $this->productMapLogsService->recordLoadEvent('refresh_local', [
                'product_count' => $this->catalogService->productCount(),
            ]);

            return redirect()
                ->route('product-map.index')
                ->with('success', 'Local product list refreshed.');
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $this->productMapLogsService->recordError($message);

            return redirect()
                ->route('product-map.index')
                ->with('error', 'Refresh failed. '.$message);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncContext(): array
    {
        $context = session(self::SYNC_CONTEXT_SESSION_KEY);

        return is_array($context) ? $context : [];
    }
}
