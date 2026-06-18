<?php

namespace App\Http\Controllers;

use App\Enums\SettlementEntryType;
use App\Http\Requests\StoreSettlementEntryRequest;
use App\Http\Requests\StoreSupplierPaymentRequest;
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
        $supplierId = $user->isSupplier()
            ? $user->supplier_id
            : ($request->query('supplier_id') ?: Supplier::query()->orderBy('name')->value('id'));

        $summary = $this->payableService->summary(
            $supplierId ? (int) $supplierId : null,
        );

        $settlements = SettlementEntry::with(['recordedBy'])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('payables.index', [
            'summary' => $summary,
            'balancePresentation' => $this->payableService->balancePresentation((float) $summary['net_payable']),
            'settlements' => $settlements,
            'suppliers' => $user->isAdmin() ? Supplier::orderBy('name')->get() : collect(),
            'selectedSupplierId' => $supplierId,
            'settlementTypes' => SettlementEntryType::cases(),
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
            ->route('payables.index', ['supplier_id' => $validated['supplier_id']])
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
