<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateOrderStatusMappingsRequest;
use App\Models\OrderStatusMapping;
use App\Services\OpenCart\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderStatusMappingController extends Controller
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
    ) {}

    public function index(): View
    {
        return view('settings.order-status-mapping', [
            'mappings' => OrderStatusMapping::orderBy('source_status_name')->get(),
        ]);
    }

    public function syncFromOpenCart(): RedirectResponse
    {
        $statuses = $this->orderStatusService->fetchFromOpenCart();
        $count = count($statuses);

        return redirect()
            ->route('settings.order-status-mapping.index')
            ->with('success', "Loaded {$count} order status(es) from OpenCart.");
    }

    public function update(UpdateOrderStatusMappingsRequest $request): RedirectResponse
    {
        foreach ($request->validated('mappings') as $mappingData) {
            OrderStatusMapping::where('id', $mappingData['id'])
                ->update(['sfm_status' => $mappingData['sfm_status']]);
        }

        return redirect()
            ->route('settings.order-status-mapping.index')
            ->with('success', 'Order status mappings saved.');
    }
}
