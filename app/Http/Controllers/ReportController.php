<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\Order;
use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\StockAdjustmentHistory;
use App\Models\ReturnModel;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\DispatchReportService;
use App\Services\OperationalDefaultsService;
use App\Services\OperationalFinanceService;
use App\Services\PayableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly PayableService $payableService,
        private readonly OperationalFinanceService $operationalFinance,
        private readonly DispatchReportService $dispatchReport,
        private readonly OperationalDefaultsService $defaults,
    ) {}

    public function dispatch(Request $request): View
    {
        $filters = $this->reportFilters($request);
        $lines = $this->dispatchReport->lines($filters);

        return view('reports.dispatch.index', array_merge([
            'lines' => $lines,
            'totals' => $this->dispatchReport->totals($lines),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'courier' => $request->query('courier'),
            'search' => $request->query('search'),
        ], $this->reportFilterViewData($request)));
    }

    public function stock(Request $request): View
    {
        $query = SupplierProduct::with('supplier')->orderBy('name');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        } elseif ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        return view('reports.stock', [
            'rows' => $query->get(),
            'suppliers' => $this->suppliersForFilter($request),
        ]);
    }

    public function orders(Request $request): View
    {
        $query = Order::with('supplier')
            ->orderByDesc('oc_created_at');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        } elseif ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('oc_created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('oc_created_at', '<=', $to);
        }

        if ($status = $request->query('sfm_status')) {
            $query->where('sfm_status', $status);
        }

        return view('reports.orders', [
            'rows' => $query->get(),
            'suppliers' => $this->suppliersForFilter($request),
            'from' => $from ?? null,
            'to' => $to ?? null,
        ]);
    }

    public function returns(Request $request): View
    {
        $filters = $this->reportFilters($request);

        $query = ReturnModel::with(['order', 'supplier', 'returnItems.orderItem', 'confirmedBy'])
            ->orderByDesc('created_at');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        } elseif (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if ($status = $request->query('status')) {
            $query->where('return_status', $status);
        } else {
            $query->where('return_status', ReturnStatus::Confirmed);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('received_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('received_date', '<=', $to);
        }

        $rows = $query->get()->map(function (ReturnModel $row) {
            $itemsSummary = $row->returnItems->map(function ($item) {
                $name = $item->orderItem?->product_name ?? 'Item';

                return $name.' ×'.$item->quantity;
            })->implode(', ');

            $qty = (int) $row->returnItems->sum('quantity');
            $returnCost = (float) $row->returnItems->sum(
                fn ($item) => $item->quantity * $item->supplier_cost_snapshot
            );

            return [
                'model' => $row,
                'date' => $row->received_date ?? $row->created_at,
                'order_no' => $row->order?->source_order_id ?? '—',
                'customer' => $row->order?->customer_name ?? '—',
                'supplier' => $row->supplier?->name ?? '—',
                'items_summary' => $itemsSummary ?: '—',
                'qty' => $qty,
                'return_cost' => $returnCost,
                'status' => $row->return_status,
            ];
        });

        return view('reports.returns', array_merge([
            'rows' => $rows,
            'totals' => [
                'orders' => $rows->count(),
                'qty' => (int) $rows->sum('qty'),
                'return_cost' => $this->operationalFinance->returnCost($filters),
            ],
            'statusFilter' => $status ?? 'confirmed',
            'from' => $from ?? null,
            'to' => $to ?? null,
        ], $this->reportFilterViewData($request)));
    }

    public function ledger(Request $request): View
    {
        $supplierId = $request->user()->isSupplier()
            ? $request->user()->supplier_id
            : ($request->query('supplier_id') ? (int) $request->query('supplier_id') : null);

        $connectionId = $request->query('connection_id') ? (int) $request->query('connection_id') : null;

        $dateRange = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        $statement = $this->payableService->accountStatement(
            $supplierId,
            $dateRange ?: null,
            $connectionId,
        );

        $summary = $this->payableService->summary(
            $supplierId,
            $dateRange ?: null,
            $connectionId,
            activeCycleOnly: true,
        );

        return view('reports.ledger', [
            'rows' => $statement,
            'summary' => $summary,
            'balancePresentation' => $this->payableService->balancePresentation((float) ($summary['net_payable'] ?? 0)),
            'suppliers' => $this->suppliersForFilter($request),
            'stores' => $request->user()->isAdmin()
                ? Connection::query()->orderBy('store_url')->get()
                : collect(),
            'types' => collect(\App\Enums\LedgerEntryType::cases())->map->value,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'selectedConnectionId' => $connectionId,
        ]);
    }

    public function payables(Request $request): View
    {
        $filters = $this->reportFilters($request);

        return view('reports.payables', array_merge([
            'rows' => $this->operationalFinance->buildPayableReportRows($filters),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'selectedConnectionId' => $filters['connection_id'] ?? null,
        ], $this->reportFilterViewData($request)));
    }

    /**
     * @return array<string, mixed>
     */
    private function reportFilterViewData(Request $request): array
    {
        return [
            'suppliers' => $this->suppliersForFilter($request),
            'stores' => $request->user()->isAdmin()
                ? Connection::query()->orderBy('store_url')->get()
                : collect(),
            'defaultSupplierId' => $this->defaults->defaultSupplierId(),
            'defaultConnectionId' => $this->defaults->defaultConnectionId(),
            'singleSupplier' => $this->defaults->hasSingleSupplier(),
            'singleStore' => $this->defaults->hasSingleStore(),
            'defaultSupplierName' => $this->defaults->defaultSupplier()->name,
            'defaultStoreLabel' => $this->defaults->storeLabel(),
        ];
    }

    /**
     * @return array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null, courier?: string|null, search?: string|null, user_supplier_id?: int|null}
     */
    private function reportFilters(Request $request): array
    {
        return $this->defaults->applyReportFilters($request);
    }

    public function productMovement(Request $request): View
    {
        $query = StockAdjustmentHistory::with(['supplier', 'changedByUser'])
            ->orderByDesc('created_at');

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $rows = $query->limit(500)->get();

        $controlStates = ProductControlState::with('variants')
            ->when($supplierId, fn ($q, $id) => $q->where('supplier_id', $id))
            ->orderBy('source_product_id')
            ->get();

        return view('reports.product-movement', [
            'rows' => $rows,
            'controlStates' => $controlStates,
            'totals' => [
                'adjustments' => $rows->count(),
                'net_change' => $rows->sum('difference'),
            ],
            'suppliers' => $this->suppliersForFilter($request),
            'from' => $from ?? null,
            'to' => $to ?? null,
        ]);
    }

    public function profitCost(Request $request): View
    {
        $query = Order::with(['supplier', 'items'])
            ->whereIn('sfm_status', [
                SfmOrderStatus::Dispatched,
                SfmOrderStatus::Completed,
            ])
            ->orderByDesc('oc_created_at');

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('oc_created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('oc_created_at', '<=', $to);
        }

        $rows = $query->get()->map(function (Order $order) {
            $supplierCost = $order->items->sum(
                fn ($item) => $item->quantity * ($item->supplier_product_cost_snapshot ?? 0)
            );

            return [
                'order' => $order,
                'sale_amount' => (float) $order->sale_amount,
                'supplier_cost' => $supplierCost,
                'margin' => (float) $order->sale_amount - $supplierCost,
            ];
        });

        return view('reports.profit-cost', [
            'rows' => $rows,
            'totals' => [
                'sale_amount' => $rows->sum('sale_amount'),
                'supplier_cost' => $rows->sum('supplier_cost'),
                'margin' => $rows->sum('margin'),
            ],
            'suppliers' => $this->suppliersForFilter($request),
            'from' => $from ?? null,
            'to' => $to ?? null,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Supplier> */
    private function suppliersForFilter(Request $request)
    {
        return $request->user()->isAdmin()
            ? Supplier::query()->where('is_active', true)->orderBy('name')->get()
            : collect();
    }
}
