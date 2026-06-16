<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductMapControlSaveRequest;
use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductControlCategoryService;
use App\Services\ProductMap\ProductControlMergeService;
use App\Services\ProductMap\ProductMapListingFilter;
use App\Services\ProductMap\ProductMapLocalControlService;
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
    ) {}

    public function index(Request $request): View
    {
        $connection = $this->connectionService->getActive();
        $preview = session('product_preview');
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
        return $this->fetchPreview('Product preview loaded.');
    }

    public function refresh(): RedirectResponse
    {
        return $this->fetchPreview('Product preview refreshed.', mergeLocal: true);
    }

    protected function fetchPreview(string $successMessage, bool $mergeLocal = false): RedirectResponse
    {
        try {
            $existing = $mergeLocal ? session('product_preview') : null;
            $preview = $this->previewService->loadPreview();

            if ($mergeLocal && is_array($existing) && ! empty($existing['products'])) {
                $preview = $this->previewService->mergeLocalPreview($preview, $existing);
            }

            $preview = $this->controlMergeService->mergeIntoPreview($preview);
            $preview = $this->previewService->refreshPreviewState($preview);

            session()->put('product_preview', $preview);

            return redirect()
                ->route('product-map.index')
                ->with('success', $successMessage);
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $hasExistingPreview = is_array(session('product_preview'));

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
