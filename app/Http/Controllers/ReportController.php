<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Enums\SfmOrderStatus;
use App\Models\Connection;
use App\Models\DispatchReport;
use App\Models\Order;
use App\Models\ProductMap\ProductControlState;
use App\Models\ProductMap\StockAdjustmentHistory;
use App\Models\ReturnModel;
use App\Models\Supplier;
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
        $user = $request->user();
        $supplierId = $user->isSupplier()
            ? $user->supplier_id
            : ($request->query('supplier_id') ? (int) $request->query('supplier_id') : null);

        $connectionId = $request->query('connection_id') ? (int) $request->query('connection_id') : null;

        $dateRange = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        $suppliersQuery = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($supplierId) {
            $suppliersQuery->where('id', $supplierId);
        }

        $suppliers = $suppliersQuery->get();

        $storesQuery = Connection::query()->orderBy('store_url');

        if ($connectionId) {
            $storesQuery->where('id', $connectionId);
        }

        $stores = $storesQuery->get();
        $rows = collect();

        foreach ($suppliers as $supplier) {
            if ($stores->isEmpty()) {
                $summary = $this->payableService->summary($supplier->id, $dateRange ?: null, null);
                $rows->push($this->payableService->buildReportRow($supplier->name, '—', $summary));

                continue;
            }

            foreach ($stores as $store) {
                $summary = $this->payableService->summary(
                    $supplier->id,
                    $dateRange ?: null,
                    $store->id,
                );

                $rows->push($this->payableService->buildReportRow(
                    $supplier->name,
                    $this->storeLabel($store),
                    $summary,
                ));
            }
        }

        return view('reports.payables', [
            'rows' => $rows,
            'suppliers' => $this->suppliersForFilter($request),
            'stores' => $user->isAdmin()
                ? Connection::query()->orderBy('store_url')->get()
                : collect(),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'selectedConnectionId' => $connectionId,
        ]);
    }

    private function storeLabel(Connection $connection): string
    {
        if (! filled($connection->store_url)) {
            return 'Store #'.$connection->id;
        }

        $host = parse_url($connection->store_url, PHP_URL_HOST);

        return $host ?: $connection->store_url;
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
