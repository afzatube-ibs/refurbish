<?php

namespace App\Http\Controllers;

use App\Enums\CollectionSource;
use App\Enums\SettlementEntryType;
use App\Http\Requests\StoreCollectionEntryRequest;
use App\Models\Connection;
use App\Models\SettlementEntry;
use App\Models\Supplier;
use App\Services\OperationalDefaultsService;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionsReportController extends Controller
{
    public function __construct(
        private readonly SettlementService $settlementService,
        private readonly OperationalDefaultsService $defaults,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->defaults->applyReportFilters($request);

        $query = SettlementEntry::query()
            ->with(['supplier', 'connection', 'recordedBy'])
            ->whereNull('settlement_batch_id')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['connection_id'])) {
            $query->where('connection_id', $filters['connection_id']);
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

        return view('reports.collections', array_merge([
            'rows' => $query->limit(500)->get(),
            'entryTypes' => [
                'received_by_supplier' => SettlementEntryType::PaidToStoreOwner,
                'payment_to_dropshipper' => SettlementEntryType::ReceivedFromSupplier,
                'adjustment' => SettlementEntryType::Adjustment,
            ],
            'sources' => CollectionSource::cases(),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'selectedSupplierId' => $filters['supplier_id'] ?? $this->defaults->defaultSupplierId(),
            'selectedConnectionId' => $filters['connection_id'] ?? $this->defaults->defaultConnectionId(),
            'selectedEntryType' => $request->query('entry_type'),
        ], $this->scopeViewData()));
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
            ->route('reports.collections')
            ->with('success', 'Collection entry recorded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function scopeViewData(): array
    {
        return [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'stores' => Connection::query()->orderBy('store_url')->get(),
            'defaultSupplierId' => $this->defaults->defaultSupplierId(),
            'defaultConnectionId' => $this->defaults->defaultConnectionId(),
            'singleSupplier' => $this->defaults->hasSingleSupplier(),
            'singleStore' => $this->defaults->hasSingleStore(),
            'defaultSupplierName' => $this->defaults->defaultSupplier()->name,
            'defaultStoreLabel' => $this->defaults->storeLabel(),
        ];
    }
}
