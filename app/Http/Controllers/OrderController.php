<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispatchOrderRequest;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\OpenCart\OrderSyncService;
use App\Services\OrderStatusEngine;
use App\Services\OrderWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderSyncService $orderSyncService,
        private readonly OrderWorkflowService $orderWorkflowService,
        private readonly OrderStatusEngine $statusEngine,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with('supplier')
            ->orderByDesc('oc_created_at')
            ->orderByDesc('id');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        }

        if ($status = $request->query('status')) {
            $query->where('sfm_status', $status);
        }

        return view('orders.index', [
            'orders' => $query->paginate(25)->withQueryString(),
            'statusFilter' => $status,
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['supplier', 'items.supplierProduct', 'dispatchReports', 'returns']);

        $activityLogs = ActivityLog::query()
            ->with('user')
            ->where('entity_type', Order::class)
            ->where('entity_id', $order->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('orders.show', [
            'order' => $order,
            'activityLogs' => $activityLogs,
            'availableTransitions' => $this->statusEngine->availableSupplierTransitions(
                $order->sfm_status ?? \App\Enums\SfmOrderStatus::New
            ),
        ]);
    }

    public function sync(): RedirectResponse
    {
        $result = $this->orderSyncService->sync();

        return redirect()
            ->route('order-map.index')
            ->with('success', "Sync complete: {$result['imported']} imported, {$result['skipped']} skipped, {$result['updated']} updated.");
    }

    public function accept(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        $this->orderWorkflowService->accept($order);

        return redirect()
            ->route('order-map.show', $order)
            ->with('success', 'Order accepted.');
    }

    public function pack(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        $this->orderWorkflowService->pack($order);

        return redirect()
            ->route('order-map.show', $order)
            ->with('success', 'Order marked as packed.');
    }

    public function dispatch(DispatchOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        $this->orderWorkflowService->dispatch(
            $order,
            $request->validated('courier'),
            $request->validated('consignment_id'),
            $request->validated('dispatch_date'),
        );

        return redirect()
            ->route('order-map.show', $order)
            ->with('success', 'Order dispatched.');
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        $this->orderWorkflowService->cancel($order);

        return redirect()
            ->route('order-map.show', $order)
            ->with('success', 'Order cancelled.');
    }
}
