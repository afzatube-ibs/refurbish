<?php

namespace App\Http\Controllers;

use App\Enums\SettlementEntryType;
use App\Http\Requests\StoreSettlementEntryRequest;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\Connection;
use App\Models\SettlementEntry;
use App\Models\Supplier;
use App\Services\PayableService;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayableController extends Controller
{
    public function __construct(
        private readonly PayableService $payableService,
        private readonly SettlementService $settlementService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $supplierId = $user->isSupplier() ? $user->supplier_id : $request->query('supplier_id');
        $connectionId = $request->query('connection_id') ? (int) $request->query('connection_id') : null;

        $dateRange = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        $summary = $this->payableService->summary(
            $supplierId ? (int) $supplierId : null,
            $dateRange ?: null,
            $connectionId,
        );

        $settlements = SettlementEntry::with(['supplier', 'recordedBy', 'connection'])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($connectionId, fn ($q) => $q->where('connection_id', $connectionId))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('payables.index', [
            'summary' => $summary,
            'settlements' => $settlements,
            'suppliers' => $user->isAdmin() ? Supplier::orderBy('name')->get() : collect(),
            'stores' => $user->isAdmin() ? Connection::query()->orderBy('store_url')->get() : collect(),
            'selectedSupplierId' => $supplierId,
            'selectedConnectionId' => $connectionId,
            'settlementTypes' => SettlementEntryType::cases(),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);
    }

    public function storeSettlement(StoreSettlementEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settlementService->record(
            (int) $validated['supplier_id'],
            SettlementEntryType::from($validated['entry_type']),
            (float) $validated['amount'],
            new \DateTimeImmutable($validated['entry_date']),
            $request->user(),
            $validated['reference'] ?? null,
            $validated['notes'] ?? null,
            isset($validated['order_id']) ? (int) $validated['order_id'] : null,
            isset($validated['connection_id']) ? (int) $validated['connection_id'] : null,
        );

        return redirect()
            ->route('payables.index', array_filter([
                'supplier_id' => $validated['supplier_id'],
                'connection_id' => $validated['connection_id'] ?? null,
            ]))
            ->with('success', 'Settlement entry recorded.');
    }

    /** @deprecated Use storeSettlement with entry_type received_from_supplier */
    public function storePayment(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->payableService->recordPayment(
            (int) $validated['supplier_id'],
            (float) $validated['amount'],
            new \DateTimeImmutable($validated['payment_date']),
            $request->user(),
            $validated['reference'] ?? null,
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('payables.index', ['supplier_id' => $validated['supplier_id']])
            ->with('success', 'Supplier payment recorded.');
    }
}
