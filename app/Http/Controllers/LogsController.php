<?php

namespace App\Http\Controllers;

use App\Services\ProductMap\ProductMapLogsService;
use Illuminate\Http\RedirectResponse;

class LogsController extends Controller
{
    public function __construct(
        private readonly ProductMapLogsService $productMapLogsService,
    ) {}

    public function clearProductMapLogs(): RedirectResponse
    {
        $this->productMapLogsService->clear();

        return $this->redirectBackWithCleared('product-map', 'Product Map diagnostic logs cleared.');
    }

    public function resetProductMap(): RedirectResponse
    {
        $this->productMapLogsService->resetProductMapSession();

        return $this->redirectBackWithCleared('product-map', 'Product Map session reset. Pending sync and diagnostic logs were cleared. Database products and control history were not changed.');
    }

    protected function redirectBackWithCleared(string $tab, string $message): RedirectResponse
    {
        return redirect()
            ->back()
            ->with('info', $message)
            ->with('logs_tab', $tab);
    }
}
