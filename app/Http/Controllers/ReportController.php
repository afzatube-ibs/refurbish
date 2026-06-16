<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Enums\SfmOrderStatus;
use App\Models\DispatchReport;
use App\Models\Order;
use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\StockAdjustmentHistory;
use App\Models\ReturnModel;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierProduct;
use App\Services\PayableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly PayableService $payableService,
    ) {}

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

    public function dispatch(Request $request): View
    {
        $query = DispatchReport::with(['order', 'supplier', 'items'])
            ->orderByDesc('dispatch_date')
            ->orderByDesc('id');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        } elseif ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('dispatch_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('dispatch_date', '<=', $to);
        }

        $rows = $query->get()->each(function (DispatchReport $row): void {
            $row->total_cost = $row->items->sum(
                fn ($item) => $item->quantity * $item->supplier_cost_snapshot
            );
        });

        return view('reports.dispatch', [
            'rows' => $rows,
            'totals' => [
                'dispatch_cost' => $rows->sum('total_cost'),
                'count' => $rows->count(),
            ],
            'suppliers' => $this->suppliersForFilter($request),
            'from' => $from ?? null,
            'to' => $to ?? null,
        ]);
    }

    public function returns(Request $request): View
    {
        $query = ReturnModel::with(['order', 'supplier', 'returnItems', 'confirmedBy'])
            ->orderByDesc('created_at');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        } elseif ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($status = $request->query('status')) {
            $query->where('return_status', $status);
        } else {
            $query->whereIn('return_status', [ReturnStatus::Pending, ReturnStatus::Confirmed]);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $rows = $query->get()->each(function (ReturnModel $row): void {
            $row->return_cost = $row->returnItems->sum(
                fn ($item) => $item->quantity * $item->supplier_cost_snapshot
            );
        });

        return view('reports.returns', [
            'rows' => $rows,
            'totals' => [
                'confirmed_cost' => $rows
                    ->filter(fn (ReturnModel $row) => $row->return_status === ReturnStatus::Confirmed)
                    ->sum('return_cost'),
                'pending_count' => $rows
                    ->filter(fn (ReturnModel $row) => $row->return_status === ReturnStatus::Pending)
                    ->count(),
            ],
            'suppliers' => $this->suppliersForFilter($request),
            'statusFilter' => $status ?? '',
            'from' => $from ?? null,
            'to' => $to ?? null,
        ]);
    }

    public function ledger(Request $request): View
    {
        $query = SupplierLedgerEntry::with('supplier')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('entry_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('entry_date', '<=', $to);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $rows = $query->get();

        return view('reports.ledger', [
            'rows' => $rows,
            'totals' => [
                'count' => $rows->count(),
                'amount' => $rows->sum('amount'),
            ],
            'suppliers' => $this->suppliersForFilter($request),
            'types' => SupplierLedgerEntry::query()->distinct()->orderBy('type')->pluck('type'),
            'from' => $from ?? null,
            'to' => $to ?? null,
        ]);
    }

    public function payables(Request $request): View
    {
        $user = $request->user();
        $supplierId = $user->isSupplier()
            ? $user->supplier_id
            : ($request->query('supplier_id') ?: null);

        $dateRange = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        $summary = $this->payableService->summary(
            $supplierId ? (int) $supplierId : null,
            $dateRange ?: null,
        );

        $rows = collect();

        if ($user->isAdmin() && ! $supplierId) {
            $rows = Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function (Supplier $supplier) use ($dateRange) {
                    $supplierSummary = $this->payableService->summary($supplier->id, $dateRange ?: null);

                    return [
                        'supplier_name' => $supplier->name,
                        'delivered_cost' => $supplierSummary['delivered_cost'],
                        'returned_cost' => $supplierSummary['returned_cost'],
                        'received_amount' => $supplierSummary['received_from_supplier'],
                        'net_payable' => $supplierSummary['net_payable'],
                    ];
                });
        }

        return view('reports.payables', [
            'summary' => [
                'delivered_cost' => $summary['delivered_cost'],
                'returned_cost' => $summary['returned_cost'],
                'received_amount' => $summary['received_from_supplier'],
                'net_payable' => $summary['net_payable'],
            ],
            'rows' => $rows,
            'suppliers' => $this->suppliersForFilter($request),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);
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
