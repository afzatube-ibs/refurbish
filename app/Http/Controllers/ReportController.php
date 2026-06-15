<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Models\DispatchReport;
use App\Models\Order;
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
        }

        return view('reports.stock', [
            'products' => $query->get(),
        ]);
    }

    public function orders(Request $request): View
    {
        $query = Order::with('supplier')
            ->orderByDesc('oc_created_at');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('oc_created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('oc_created_at', '<=', $to);
        }

        return view('reports.orders', [
            'orders' => $query->get(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function dispatch(Request $request): View
    {
        $query = DispatchReport::with(['order', 'supplier', 'items'])
            ->orderByDesc('dispatch_date');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('dispatch_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('dispatch_date', '<=', $to);
        }

        return view('reports.dispatch', [
            'reports' => $query->get(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function returns(Request $request): View
    {
        $query = ReturnModel::with(['order', 'supplier', 'returnItems'])
            ->orderByDesc('created_at');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        }

        if ($status = $request->query('status')) {
            $query->where('return_status', $status);
        }

        return view('reports.returns', [
            'returns' => $query->get(),
            'statusFilter' => $status ?? ReturnStatus::Pending->value,
        ]);
    }

    public function payables(Request $request): View
    {
        $user = $request->user();
        $supplierId = $user->isSupplier()
            ? $user->supplier_id
            : ($request->query('supplier_id') ?? Supplier::query()->where('is_active', true)->value('id'));

        $dateRange = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        $summary = $this->payableService->summary(
            $supplierId ? (int) $supplierId : null,
            $dateRange ?: null,
        );

        return view('reports.payables', [
            'summary' => $summary,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);
    }
}
