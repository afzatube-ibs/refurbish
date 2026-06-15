<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\PayableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayableController extends Controller
{
    public function __construct(
        private readonly PayableService $payableService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $supplierId = $user->isSupplier() ? $user->supplier_id : $request->query('supplier_id');

        $dateRange = array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        $summary = $this->payableService->summary(
            $supplierId ? (int) $supplierId : null,
            $dateRange ?: null,
        );

        $payments = SupplierPayment::with(['supplier', 'recordedBy'])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('payment_date')
            ->limit(20)
            ->get();

        return view('payables.index', [
            'summary' => $summary,
            'payments' => $payments,
            'suppliers' => $user->isAdmin() ? Supplier::orderBy('name')->get() : collect(),
            'selectedSupplierId' => $supplierId,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);
    }

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
            ->route('payables.index', ['supplier_id' => $request->validated('supplier_id')])
            ->with('success', 'Supplier payment recorded.');
    }
}
