<?php

namespace App\Http\Controllers;

use App\Models\DispatchReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispatchReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = DispatchReport::with(['order', 'supplier', 'creator'])
            ->orderByDesc('dispatch_date')
            ->orderByDesc('id');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('dispatch_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('dispatch_date', '<=', $to);
        }

        return view('dispatch.index', [
            'reports' => $query->paginate(25)->withQueryString(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function show(Request $request, DispatchReport $report): View
    {
        if ($request->user()->isSupplier() && $report->supplier_id !== $request->user()->supplier_id) {
            abort(403);
        }

        $report->load(['order', 'supplier', 'creator', 'items.orderItem']);

        return view('dispatch.show', [
            'report' => $report,
        ]);
    }
}
