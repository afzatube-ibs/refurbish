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
            'mappings' => OrderStatusMapping::query()
                ->orderByDesc('oc_selected')
                ->orderBy('source_status_name')
                ->get(),
        ]);
    }

    public function syncFromOpenCart(): RedirectResponse
    {
        $result = $this->orderStatusService->fetchFromOpenCart();

        return redirect()
            ->route('settings.order-status-mapping.index')
            ->with('success', sprintf(
                'Loaded %d selected order status(es) from OpenCart (%d total).',
                $result['selected'],
                $result['total']
            ));
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
