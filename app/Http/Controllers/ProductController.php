<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSupplierProductRequest;
use App\Models\Connection;
use App\Models\SupplierProduct;
use App\Services\OpenCart\ProductSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductSyncService $productSyncService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierProduct::class);

        $products = SupplierProduct::with(['supplier', 'variants'])
            ->orderBy('name')
            ->paginate(50);

        return view('products.index', [
            'products' => $products,
            'hasMore' => (bool) session('product_has_more', false),
            'syncPage' => Connection::getInstance()->product_sync_page,
        ]);
    }

    public function sync(): RedirectResponse
    {
        $this->authorize('viewAny', SupplierProduct::class);

        $result = $this->productSyncService->syncNewBatch();

        $message = match (true) {
            $result['imported'] > 0 => "Imported {$result['imported']} new product(s).",
            default => 'No new products to import.',
        };

        if ($this->productSyncService->hasMore()) {
            $message .= ' More products available — use Continue Sync.';
        }

        return redirect()
            ->route('product-map.index')
            ->with('success', $message)
            ->with('product_has_more', $result['has_more']);
    }

    public function refresh(): RedirectResponse
    {
        $this->authorize('viewAny', SupplierProduct::class);

        $result = $this->productSyncService->refreshBatch();

        return redirect()
            ->route('product-map.index')
            ->with('success', "Refreshed {$result['refreshed']} product(s) from Lokkisona.")
            ->with('product_has_more', $result['has_more']);
    }

    public function update(UpdateSupplierProductRequest $request, SupplierProduct $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return redirect()
            ->route('product-map.index')
            ->with('success', 'Product supplier fields updated.');
    }
}
