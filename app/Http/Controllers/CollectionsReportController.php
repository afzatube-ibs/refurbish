<?php

namespace App\Http\Controllers;

use App\Enums\CollectionSource;
use App\Enums\SettlementEntryType;
use App\Http\Requests\StoreCollectionEntryRequest;
use App\Models\Connection;
use App\Models\SettlementEntry;
use App\Models\Supplier;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionsReportController extends Controller
{
    public function __construct(
        private readonly SettlementService $settlementService,
    ) {}

    public function index(Request $request): View
    {
        $query = SettlementEntry::query()
            ->with(['supplier', 'connection', 'recordedBy'])
            ->whereNull('settlement_batch_id')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($connectionId = $request->query('connection_id')) {
            $query->where('connection_id', (int) $connectionId);
        }

        if ($type = $request->query('entry_type')) {
            try {
                $query->where('entry_type', SettlementEntryType::fromOperationalKey($type));
            } catch (\InvalidArgumentException) {
                // ignore invalid filter
            }
        }

        if ($from = $request->query('from')) {
            $query->whereDate('entry_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('entry_date', '<=', $to);
        }

        return view('reports.collections', [
            'rows' => $query->limit(500)->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'stores' => Connection::query()->orderBy('store_url')->get(),
            'entryTypes' => [
                'received_by_supplier' => SettlementEntryType::PaidToStoreOwner,
                'payment_to_dropshipper' => SettlementEntryType::ReceivedFromSupplier,
                'adjustment' => SettlementEntryType::Adjustment,
            ],
            'sources' => CollectionSource::cases(),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'selectedSupplierId' => $request->query('supplier_id'),
            'selectedConnectionId' => $request->query('connection_id'),
            'selectedEntryType' => $request->query('entry_type'),
        ]);
    }

    public function store(StoreCollectionEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $entryType = SettlementEntryType::fromOperationalKey($validated['entry_type']);

        $this->settlementService->record(
            (int) $validated['supplier_id'],
            $entryType,
            (float) $validated['amount'],
            new \DateTimeImmutable($validated['entry_date']),
            $request->user(),
            $validated['reference'] ?? null,
            $validated['notes'] ?? null,
            null,
            isset($validated['connection_id']) ? (int) $validated['connection_id'] : null,
            $validated['collection_source'] ?? null,
        );

        return redirect()
            ->route('reports.collections', $request->only(['supplier_id', 'connection_id', 'from', 'to']))
            ->with('success', 'Collection entry recorded.');
    }
}
