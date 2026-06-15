<?php

namespace App\Http\Controllers;

use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\ProductPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductMapController extends Controller
{
    public function __construct(
        private readonly ProductPreviewService $previewService,
        private readonly ConnectionService $connectionService,
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
        ]);
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
