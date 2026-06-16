<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductMapControlSaveRequest;
use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductControlCategoryService;
use App\Services\ProductMap\ProductControlMergeService;
use App\Services\ProductMap\ProductMapListingFilter;
use App\Services\ProductMap\ProductMapLocalControlService;
use App\Services\ProductMap\ProductMapLogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductMapController extends Controller
{
    public function __construct(
        private readonly ProductPreviewService $previewService,
        private readonly ConnectionService $connectionService,
        private readonly ProductMapLocalControlService $localControlService,
        private readonly ProductControlMergeService $controlMergeService,
        private readonly ProductControlCategoryService $categoryService,
        private readonly ProductMapListingFilter $listingFilter,
        private readonly ProductMapLogsService $productMapLogsService,
    ) {}

    public function index(Request $request): View
    {
        $connection = $this->connectionService->getActive();
        $preview = session('product_preview');

        if (is_array($preview) && ! empty($preview['products'])) {
            $preview = $this->controlMergeService->mergeIntoPreview($preview);
            $preview = $this->previewService->refreshPreviewState($preview);
            session()->put('product_preview', $preview);
        }

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
        $preview = session('product_preview');

        if (! is_array($preview) || empty($preview['products'])) {
            return response()->json([
                'success' => false,
                'message' => 'Load products before saving local control changes.',
            ], 422);
        }

        try {
            $productIndex = (int) $request->validated('product_index');
            $preview = $this->localControlService->save(
                $preview,
                $productIndex,
                $request->only(['changes']),
                $request->user(),
            );

            session()->put('product_preview', $preview);

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
            $existing = session('product_preview');
            $existingProducts = is_array($existing) ? ($existing['products'] ?? []) : [];
            $freshProducts = is_array($fresh['products'] ?? null) ? $fresh['products'] : [];
            $newProducts = $this->previewService->detectNewProducts($freshProducts, $existingProducts);

            if ($newProducts === []) {
                $message = $existingProducts === []
                    ? 'No products found in OpenCart.'
                    : 'No new products found.';

                return redirect()
                    ->route('product-map.index')
                    ->with('info', $message);
            }

            session()->put('product_map_pending_load', [
                'count' => count($newProducts),
                'products' => $newProducts,
                'fetch_meta' => is_array($fresh['meta'] ?? null) ? $fresh['meta'] : [],
                'fetch_diagnostics' => is_array($fresh['diagnostics'] ?? null) ? $fresh['diagnostics'] : [],
            ]);

            $this->productMapLogsService->recordLoadEvent('load_detect', [
                'new_count' => count($newProducts),
            ]);

            $count = count($newProducts);
            $message = $count === 1
                ? '1 new product found'
                : $count.' new products found';

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
                ->with('error', 'No pending products to add. Use Load Products first.');
        }

        try {
            $existing = session('product_preview');
            $base = is_array($existing) ? $existing : [
                'products' => [],
                'activity' => [],
                'meta' => [],
                'summary' => [],
                'diagnostics' => [],
            ];

            $preview = $this->previewService->appendNewProducts($base, $pending['products']);

            if (is_array($pending['fetch_meta'] ?? null) && $pending['fetch_meta'] !== []) {
                $preview['meta'] = array_merge(
                    $pending['fetch_meta'],
                    is_array($preview['meta'] ?? null) ? $preview['meta'] : [],
                );
                $preview['meta']['loaded_at'] = now()->toIso8601String();
            }

            if (is_array($pending['fetch_diagnostics'] ?? null) && $pending['fetch_diagnostics'] !== []) {
                $preview['diagnostics'] = $pending['fetch_diagnostics'];
            }

            $preview = $this->controlMergeService->mergeIntoPreview($preview);
            $preview = $this->previewService->refreshPreviewState($preview);

            session()->put('product_preview', $preview);
            session()->forget('product_map_pending_load');

            $this->productMapLogsService->recordLoadEvent('load_confirm', [
                'added_count' => (int) ($pending['count'] ?? count($pending['products'])),
            ]);

            $count = (int) ($pending['count'] ?? count($pending['products']));
            $message = $count === 1
                ? '1 product added to Product Map.'
                : $count.' products added to Product Map.';

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
            ->with('info', 'Add to Product Map cancelled.');
    }

    public function refresh(): RedirectResponse
    {
        $existing = session('product_preview');
        $existingProducts = is_array($existing) ? ($existing['products'] ?? []) : [];

        if ($existingProducts === []) {
            return redirect()
                ->route('product-map.index')
                ->with('error', 'No products loaded yet. Please use Load Products first.');
        }

        try {
            $preview = $this->previewService->refreshExistingPreview($existing);
            $preview = $this->controlMergeService->mergeIntoPreview($preview);
            $preview = $this->previewService->refreshPreviewState($preview);

            session()->put('product_preview', $preview);

            $this->productMapLogsService->recordLoadEvent('refresh', [
                'product_count' => count($preview['products'] ?? []),
            ]);

            return redirect()
                ->route('product-map.index')
                ->with('success', 'Product preview refreshed.');
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $hasExistingPreview = is_array(session('product_preview'));
            $this->productMapLogsService->recordError($message);

            return redirect()
                ->route('product-map.index')
                ->with(
                    'error',
                    $hasExistingPreview
                        ? 'Refresh failed — showing last loaded preview. '.$message
                        : $message
                );
        }
    }
}
