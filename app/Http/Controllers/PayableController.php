<?php

namespace App\Http\Controllers;

use App\Enums\SettlementEntryType;
use App\Http\Requests\CloseSettlementBatchRequest;
use App\Http\Requests\StoreSettlementEntryRequest;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\Connection;
use App\Models\SettlementEntry;
use App\Models\Supplier;
use App\Services\PayableService;
use App\Services\SettlementBatchService;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PayableController extends Controller
{
    public function __construct(
        private readonly PayableService $payableService,
        private readonly SettlementService $settlementService,
        private readonly SettlementBatchService $batchService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $supplierId = $user->isSupplier()
            ? $user->supplier_id
            : ($request->query('supplier_id') ?: Supplier::query()->orderBy('name')->value('id'));

        $connectionId = $request->query('connection_id') ? (int) $request->query('connection_id') : null;

        $summary = $this->payableService->summary(
            $supplierId ? (int) $supplierId : null,
            null,
            $connectionId,
            activeCycleOnly: true,
        );

        $balance = (float) ($summary['net_payable'] ?? 0);

        $settlements = SettlementEntry::with(['recordedBy'])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($connectionId, fn ($q) => $q->where('connection_id', $connectionId))
            ->whereNull('settlement_batch_id')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('payables.index', [
            'summary' => $summary,
            'balancePresentation' => $this->payableService->balancePresentation($balance),
            'canCloseSettlement' => round($balance, 2) != 0.0,
            'settlements' => $settlements,
            'suppliers' => $user->isAdmin() ? Supplier::orderBy('name')->get() : collect(),
            'stores' => $user->isAdmin() ? Connection::query()->orderBy('store_url')->get() : collect(),
            'selectedSupplierId' => $supplierId,
            'selectedConnectionId' => $connectionId,
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
            ->route('payables.index', array_filter([
                'supplier_id' => $validated['supplier_id'],
                'connection_id' => $validated['connection_id'] ?? null,
            ]))
            ->with('success', 'Settlement entry recorded.');
    }

    public function closeSettlement(CloseSettlementBatchRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $batch = $this->batchService->close(
                (int) $validated['supplier_id'],
                $request->user(),
                isset($validated['connection_id']) ? (int) $validated['connection_id'] : null,
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('payables.index', array_filter([
                    'supplier_id' => $validated['supplier_id'],
                    'connection_id' => $validated['connection_id'] ?? null,
                ]))
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('settlements.show', $batch)
            ->with('success', 'Settlement batch '.$batch->batch_no.' closed.');
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
