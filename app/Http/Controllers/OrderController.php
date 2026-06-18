<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispatchOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\StoreManualOrderRequest;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\OpenCart\OrderSyncService;
use App\Services\OrderMap\ManualOrderProductSearchService;
use App\Services\OrderMap\ManualOrderService;
use App\Services\OrderMap\OrderConnectorAuditService;
use App\Services\OrderMap\OrderMapLoadLogService;
use App\Services\OrderMap\OrderQueuePresenter;
use App\Services\OrderMap\PackingInvoicePresenter;
use App\Services\OrderStatusEngine;
use App\Services\OrderWorkflowService;
use App\Services\SupplierLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderSyncService $orderSyncService,
        private readonly OrderWorkflowService $orderWorkflowService,
        private readonly OrderStatusEngine $statusEngine,
        private readonly OrderQueuePresenter $queuePresenter,
        private readonly OrderMapLoadLogService $loadLogService,
        private readonly ManualOrderService $manualOrderService,
        private readonly ManualOrderProductSearchService $manualOrderProductSearchService,
        private readonly OrderConnectorAuditService $connectorAuditService,
        private readonly PackingInvoicePresenter $packingInvoicePresenter,
        private readonly SupplierLedgerService $ledgerService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        if ($request->boolean('dismiss_audit')) {
            session()->forget('order_connector_audit_visible');
        }

        $query = Order::with(['items'])
            ->orderByDesc('oc_created_at')
            ->orderByDesc('id');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        }

        if ($status = $request->query('status')) {
            $query->where('sfm_status', $status);
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('order-map.index', [
            'orders' => $orders,
            'queueRows' => $orders->getCollection()->map(fn (Order $order) => $this->queuePresenter->present($order)),
            'statusFilter' => $status,
            'lastSync' => $this->loadLogService->last(),
            'lastConnectorAudit' => $this->connectorAuditService->last(),
            'loadLogService' => $this->loadLogService,
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['supplier', 'items', 'dispatchReports']);

        $activityLogs = ActivityLog::query()
            ->with('user')
            ->where('entity_type', Order::class)
            ->where('entity_id', $order->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('order-map.show', [
            'order' => $order,
            'queueRow' => $this->queuePresenter->present($order),
            'activityLogs' => $activityLogs,
            'availableTransitions' => $this->statusEngine->availableSupplierTransitions(
                $order->sfm_status ?? \App\Enums\SfmOrderStatus::New
            ),
            'canEdit' => $this->statusEngine->canEditOrder($order),
            'settlementHistory' => $this->ledgerService->orderHistory($order->id),
        ]);
    }

    public function panel(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['supplier', 'items']);

        return view('order-map.partials.detail-panel', [
            'order' => $order,
            'queueRow' => $this->queuePresenter->present($order),
            'availableTransitions' => $this->statusEngine->availableSupplierTransitions(
                $order->sfm_status ?? \App\Enums\SfmOrderStatus::New
            ),
            'canEdit' => $this->statusEngine->canEditOrder($order),
            'compactHeader' => true,
            'settlementHistory' => $this->ledgerService->orderHistory($order->id),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $order->update($request->validated());

        return redirect()->back()->with('success', 'Order updated.');
    }

    public function create(): View
    {
        $this->authorize('viewAny', Order::class);

        return view('order-map.create', [
            'sourceStores' => [
                'lokkisona' => 'Lokkisona',
            ],
            'sourceTypes' => [
                'inbox' => 'Inbox',
                'phone' => 'Phone',
                'offline' => 'Offline',
                'other' => 'Other',
            ],
        ]);
    }

    public function searchManualProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $query = trim((string) $request->query('q', ''));

        return response()->json([
            'results' => $this->manualOrderProductSearchService->search($query),
        ]);
    }

    public function store(StoreManualOrderRequest $request): RedirectResponse
    {
        $order = $this->manualOrderService->create($request->validated(), $request->user());

        return redirect()
            ->route('order-map.index')
            ->with('success', 'Order '.$order->source_order_id.' created.');
    }

    public function load(): RedirectResponse
    {
        $result = $this->orderSyncService->loadNewOrders(auth()->user());

        return redirect()
            ->route('order-map.index')
            ->with('success', $this->loadLogService->formatBannerMessage($result));
    }

    public function syncStatusUpdates(): RedirectResponse
    {
        $result = $this->orderSyncService->syncStatusUpdates(auth()->user());

        return redirect()
            ->route('order-map.index')
            ->with('success', $this->loadLogService->formatBannerMessage($result));
    }

    public function auditConnector(): RedirectResponse
    {
        try {
            $audit = $this->connectorAuditService->runImportTriggerAudit(auth()->user());

            session(['order_connector_audit_visible' => true]);

            return redirect()
                ->route('order-map.index')
                ->with('success', $this->connectorAuditService->formatBannerMessage($audit));
        } catch (\Throwable $exception) {
            return redirect()
                ->route('order-map.index')
                ->with('error', 'Connector audit failed: '.$exception->getMessage());
        }
    }

    /** @deprecated Use load() */
    public function sync(): RedirectResponse
    {
        return $this->load();
    }

    public function accept(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $this->orderWorkflowService->accept($order);

        return redirect()->back()->with('success', 'Order accepted.');
    }

    public function pack(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $this->orderWorkflowService->pack($order);

        return redirect()->back()->with('success', 'Order marked as packed.');
    }

    public function dispatch(DispatchOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        $this->orderWorkflowService->dispatch(
            $order,
            (string) ($request->validated('courier') ?? ''),
            $request->validated('consignment_id'),
            $request->validated('dispatch_date'),
        );

        return redirect()->back()->with('success', 'Order dispatched.');
    }

    public function reject(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $this->orderWorkflowService->reject($order, auth()->user());

        return redirect()->back()->with('success', 'Order rejected and stock restored.');
    }

    public function returnQueue(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $this->orderWorkflowService->moveToReturnQueue($order);

        return redirect()->back()->with('success', 'Order moved to return queue.');
    }

    public function returnReceived(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $this->orderWorkflowService->markReturnReceived($order);

        return redirect()->back()->with('success', 'Return marked as received.');
    }

    public function complete(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);
        $this->orderWorkflowService->complete($order);

        return redirect()->back()->with('success', 'Order completed.');
    }

    public function printInvoice(Order $order): View
    {
        $this->authorize('view', $order);

        return view('order-map.print-invoice', [
            'invoice' => $this->packingInvoicePresenter->present($order),
        ]);
    }
}
