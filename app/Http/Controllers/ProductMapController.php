<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductMapControlSaveRequest;
use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\ProductPreviewService;
use App\Services\ProductMap\ProductMapLocalControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductMapController extends Controller
{
    public function __construct(
        private readonly ProductPreviewService $previewService,
        private readonly ConnectionService $connectionService,
        private readonly ProductMapLocalControlService $localControlService,
    ) {}

    public function index(): View
    {
        $connection = $this->connectionService->getActive();
        $preview = session('product_preview');

        return view('product-map.preview', [
            'connection' => $connection,
            'connectionReady' => $connection->is_active && filled($connection->store_url) && filled($connection->api_token),
            'preview' => is_array($preview) ? $preview : null,
            'products' => is_array($preview) ? ($preview['products'] ?? []) : [],
            'previewMeta' => is_array($preview) ? ($preview['meta'] ?? null) : null,
            'previewSummary' => is_array($preview) ? ($preview['summary'] ?? null) : null,
            'previewDiagnostics' => is_array($preview) ? ($preview['diagnostics'] ?? null) : null,
            'previewActivity' => is_array($preview) ? ($preview['activity'] ?? []) : [],
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
                $request->only(['parent', 'variants']),
                $request->user(),
            );

            session()->put('product_preview', $preview);

            $product = $preview['products'][$productIndex] ?? null;
            $productId = (string) ($product['product_id'] ?? $product['oc_product_id'] ?? '');

            return response()->json([
                'success' => true,
                'message' => 'Product control saved locally.',
                'product_index' => $productIndex,
                'product' => $product,
                'summary' => $preview['summary'] ?? [],
                'activity' => $this->localControlService->activityForProduct($preview, $productId),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function load(): RedirectResponse
    {
        return $this->fetchPreview('Product preview loaded.');
    }

    public function refresh(): RedirectResponse
    {
        return $this->fetchPreview('Product preview refreshed.');
    }

    protected function fetchPreview(string $successMessage): RedirectResponse
    {
        try {
            $preview = $this->previewService->loadPreview();
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
