<?php

namespace App\Http\Controllers;

use App\Models\SettlementBatch;
use App\Models\Supplier;
use App\Services\SettlementBatchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettlementBatchController extends Controller
{
    public function __construct(
        private readonly SettlementBatchService $batchService,
    ) {}

    public function index(Request $request): View
    {
        $supplierId = $request->query('supplier_id') ? (int) $request->query('supplier_id') : null;

        $batches = SettlementBatch::query()
            ->with(['supplier', 'connection', 'closedBy'])
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('settlements.index', [
            'batches' => $batches,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedSupplierId' => $supplierId,
        ]);
    }

    public function show(SettlementBatch $settlement): View
    {
        $detail = $this->batchService->detail($settlement);

        return view('settlements.show', $detail);
    }
}
