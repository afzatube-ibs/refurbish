<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDispatchBatchRequest;
use App\Models\Connection;
use App\Models\DispatchBatch;
use App\Models\Supplier;
use App\Services\DispatchBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class DispatchBatchController extends Controller
{
    public function __construct(
        private readonly DispatchBatchService $batchService,
    ) {}

    public function show(Request $request, DispatchBatch $batch): View
    {
        $this->authorizeBatch($request, $batch);

        $batch->load(['supplier', 'connection', 'creator', 'batchOrders', 'batchItems']);

        $itemsByOrder = $batch->batchItems->groupBy('order_id');
        $orderRows = $batch->batchOrders->map(function ($batchOrder) use ($itemsByOrder) {
            return [
                'order' => $batchOrder,
                'items' => $itemsByOrder->get($batchOrder->order_id, collect()),
            ];
        });

        return view('reports.dispatch.show', [
            'batch' => $batch,
            'orderRows' => $orderRows,
        ]);
    }

    public function print(Request $request, DispatchBatch $batch): View
    {
        $this->authorizeBatch($request, $batch);

        $batch->load(['supplier', 'connection', 'batchOrders', 'batchItems']);

        $lineRows = collect();
        foreach ($batch->batchOrders as $batchOrder) {
            $items = $batch->batchItems->where('order_id', $batchOrder->order_id);
            foreach ($items as $item) {
                $lineRows->push([
                    'order_no' => $batchOrder->order_no,
                    'customer_name' => $batchOrder->customer_name,
                    'phone' => $batchOrder->phone,
                    'product_name' => $item->product_name,
                    'model' => $item->model,
                    'ibs_model' => $item->ibs_model,
                    'qty' => $item->qty,
                    'supplier_unit_cost' => $item->supplier_unit_cost,
                    'supplier_total_cost' => $item->supplier_total_cost,
                    'courier' => $batchOrder->courier,
                    'consignment_id' => $batchOrder->consignment_id,
                    'cost_status' => $item->cost_status,
                ]);
            }
        }

        return view('reports.dispatch.print', [
            'batch' => $batch,
            'lineRows' => $lineRows,
        ]);
    }

    public function storeCreateBatch(CreateDispatchBatchRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $batch = $this->batchService->createFromPackedOrders([
                'order_ids' => $validated['order_ids'],
                'dispatch_date' => $validated['dispatch_date'] ?? null,
                'orders' => $validated['orders'],
            ], $request->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('order-map.index', ['status' => 'packed'])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('reports.dispatch.show', $batch)
            ->with('success', 'Dispatch batch '.$batch->batch_no.' created.');
    }

    protected function authorizeBatch(Request $request, DispatchBatch $batch): void
    {
        if ($request->user()->isSupplier() && $batch->supplier_id !== $request->user()->supplier_id) {
            abort(403);
        }
    }

    private function suppliersForFilter(Request $request)
    {
        if ($request->user()->isSupplier()) {
            return collect();
        }

        return Supplier::query()->where('is_active', true)->orderBy('name')->get();
    }
}
