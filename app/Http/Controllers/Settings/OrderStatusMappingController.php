<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SfmOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateOrderStatusMappingsRequest;
use App\Models\OrderStatusMapping;
use App\Services\OpenCart\OrderStatusService;
use App\Services\OrderMap\OrderStatusMappingGuide;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderStatusMappingController extends Controller
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly OrderStatusMappingGuide $mappingGuide,
    ) {}

    public function index(): View
    {
        $all = OrderStatusMapping::query()
            ->orderBy('source_status_name')
            ->get();

        return view('settings.order-status-mapping', [
            'activeMappings' => $all->where('oc_selected', true)->values(),
            'referenceMappings' => $all->where('oc_selected', false)->values(),
            'recommendedRows' => $this->mappingGuide->recommendedRows(),
            'recommendedJson' => collect(OrderStatusMappingGuide::RECOMMENDED_BY_OC_ID)
                ->map(fn (SfmOrderStatus $status) => $status->value)
                ->all(),
            'guide' => $this->mappingGuide,
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
        $indexed = OrderStatusMapping::query()->get()->keyBy('id');

        foreach ($request->validated('mappings') as $mappingData) {
            $mapping = $indexed->get($mappingData['id']);

            if (! $mapping instanceof OrderStatusMapping) {
                continue;
            }

            $status = $mapping->oc_selected
                ? (string) $mappingData['sfm_status']
                : SfmOrderStatus::Ignore->value;

            $mapping->update(['sfm_status' => $status]);
        }

        return redirect()
            ->route('settings.order-status-mapping.index')
            ->with('success', 'Order status mappings saved.');
    }
}
