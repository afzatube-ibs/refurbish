<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Enums\SettlementEntryType;
use App\Models\Connection;
use App\Models\DispatchBatchItem;
use App\Models\DispatchReportItem;
use App\Models\ReturnItem;
use App\Models\SettlementEntry;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class OperationalFinanceService
{
    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public function dispatchCost(array $filters = []): float
    {
        $batchCost = $this->batchDispatchCostQuery($filters)
            ->selectRaw('COALESCE(SUM(dispatch_batch_items.supplier_total_cost), 0) as total')
            ->value('total');

        $legacyCost = $this->legacyDispatchCostQuery($filters)
            ->selectRaw('COALESCE(SUM(dispatch_report_items.quantity * dispatch_report_items.supplier_cost_snapshot), 0) as total')
            ->value('total');

        return round((float) $batchCost + (float) $legacyCost, 2);
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public function returnCost(array $filters = []): float
    {
        $total = $this->returnItemsQuery($filters)
            ->selectRaw('COALESCE(SUM(return_items.quantity * return_items.supplier_cost_snapshot), 0) as total')
            ->value('total');

        return round((float) $total, 2);
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public function receivedBySupplier(array $filters = []): float
    {
        return $this->sumSettlementEntries(SettlementEntryType::PaidToStoreOwner, $filters);
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public function paymentToDropshipper(array $filters = []): float
    {
        return $this->sumSettlementEntries(SettlementEntryType::ReceivedFromSupplier, $filters);
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public function adjustments(array $filters = []): float
    {
        $query = $this->settlementEntriesQuery($filters)
            ->where('entry_type', SettlementEntryType::Adjustment);

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public function currentPayable(array $filters = []): float
    {
        return $this->applyFormula(
            $this->dispatchCost($filters),
            $this->returnCost($filters),
            $this->receivedBySupplier($filters),
            $this->paymentToDropshipper($filters),
            $this->adjustments($filters),
        );
    }

    public function payableMeaning(float $amount): string
    {
        $amount = round($amount, 2);

        if ($amount > 0) {
            return 'Need to pay supplier';
        }

        if ($amount < 0) {
            return 'Overpaid / review needed';
        }

        return 'Settled';
    }

    public function payableTone(float $amount): string
    {
        $amount = round($amount, 2);

        if ($amount > 0) {
            return 'positive';
        }

        if ($amount < 0) {
            return 'negative';
        }

        return 'zero';
    }

    public function payableToneClass(float $amount): string
    {
        return match ($this->payableTone($amount)) {
            'positive' => 'text-orange-600',
            'negative' => 'text-sky-700',
            default => 'text-emerald-700',
        };
    }

    /**
     * @return array{amount: float, meaning: string, tone: string, tone_class: string}
     */
    public function payablePresentation(float $amount): array
    {
        $amount = round($amount, 2);

        return [
            'amount' => $amount,
            'meaning' => $this->payableMeaning($amount),
            'tone' => $this->payableTone($amount),
            'tone_class' => $this->payableToneClass($amount),
        ];
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function buildPayableReportRows(array $filters = []): array
    {
        $supplierId = $filters['supplier_id'] ?? null;
        $connectionId = $filters['connection_id'] ?? null;

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
        $rows = [];

        foreach ($suppliers as $supplier) {
            if ($stores->isEmpty()) {
                $rowFilters = array_merge($filters, ['supplier_id' => $supplier->id, 'connection_id' => null]);
                $rows[] = $this->buildReportRow($supplier->name, '—', $rowFilters);

                continue;
            }

            foreach ($stores as $store) {
                $rowFilters = array_merge($filters, [
                    'supplier_id' => $supplier->id,
                    'connection_id' => $store->id,
                ]);
                $rows[] = $this->buildReportRow(
                    $supplier->name,
                    $this->storeLabel($store),
                    $rowFilters,
                );
            }
        }

        return $rows;
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function buildReportRow(string $supplierName, string $storeName, array $filters): array
    {
        $dispatch = $this->dispatchCost($filters);
        $returns = $this->returnCost($filters);
        $received = $this->receivedBySupplier($filters);
        $payment = $this->paymentToDropshipper($filters);
        $adjustment = $this->adjustments($filters);
        $payable = $this->applyFormula($dispatch, $returns, $received, $payment, $adjustment);
        $presentation = $this->payablePresentation($payable);

        return [
            'supplier_name' => $supplierName,
            'store_name' => $storeName,
            'dispatch_cost' => $dispatch,
            'return_cost' => $returns,
            'received_by_supplier' => $received,
            'payment_to_dropshipper' => $payment,
            'adjustment' => $adjustment,
            'current_payable' => $payable,
            'payable_meaning' => $presentation['meaning'],
            'payable_tone' => $presentation['tone'],
            'payable_tone_class' => $presentation['tone_class'],
        ];
    }

    public function applyFormula(
        float $dispatchCost,
        float $returnCost,
        float $receivedBySupplier,
        float $paymentToDropshipper,
        float $adjustment,
    ): float {
        return round(
            $dispatchCost
            - $returnCost
            - $receivedBySupplier
            - $paymentToDropshipper
            + $adjustment,
            2
        );
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    protected function sumSettlementEntries(SettlementEntryType $type, array $filters): float
    {
        $total = $this->settlementEntriesQuery($filters)
            ->where('entry_type', $type)
            ->sum('amount');

        return round((float) $total, 2);
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    protected function settlementEntriesQuery(array $filters)
    {
        $query = SettlementEntry::query()->whereNull('settlement_batch_id');

        if ($filters['supplier_id'] ?? null) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if ($filters['connection_id'] ?? null) {
            $query->where('connection_id', $filters['connection_id']);
        }

        if ($filters['from'] ?? null) {
            $query->whereDate('entry_date', '>=', $filters['from']);
        }

        if ($filters['to'] ?? null) {
            $query->whereDate('entry_date', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    protected function batchDispatchCostQuery(array $filters)
    {
        $query = DispatchBatchItem::query()
            ->join('dispatch_batches', 'dispatch_batches.id', '=', 'dispatch_batch_items.dispatch_batch_id');

        if ($filters['supplier_id'] ?? null) {
            $query->where('dispatch_batches.supplier_id', $filters['supplier_id']);
        }

        if ($filters['connection_id'] ?? null) {
            $query->where('dispatch_batches.connection_id', $filters['connection_id']);
        }

        if ($filters['from'] ?? null) {
            $query->whereDate('dispatch_batches.dispatch_date', '>=', $filters['from']);
        }

        if ($filters['to'] ?? null) {
            $query->whereDate('dispatch_batches.dispatch_date', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    protected function legacyDispatchCostQuery(array $filters)
    {
        $query = DispatchReportItem::query()
            ->join('dispatch_reports', 'dispatch_reports.id', '=', 'dispatch_report_items.dispatch_report_id')
            ->whereNull('dispatch_reports.dispatch_batch_id');

        if ($filters['connection_id'] ?? null) {
            if ((int) $filters['connection_id'] !== $this->defaultConnectionId()) {
                return DispatchReportItem::query()->whereRaw('0 = 1');
            }
        }

        if ($filters['supplier_id'] ?? null) {
            $query->where('dispatch_reports.supplier_id', $filters['supplier_id']);
        }

        if ($filters['from'] ?? null) {
            $query->whereDate('dispatch_reports.dispatch_date', '>=', $filters['from']);
        }

        if ($filters['to'] ?? null) {
            $query->whereDate('dispatch_reports.dispatch_date', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @param  array{supplier_id?: int|null, connection_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    protected function returnItemsQuery(array $filters)
    {
        $query = ReturnItem::query()
            ->whereHas('returnRecord', function ($returnQuery) use ($filters) {
                $returnQuery->where('return_status', ReturnStatus::Confirmed);

                if ($filters['supplier_id'] ?? null) {
                    $returnQuery->where('supplier_id', $filters['supplier_id']);
                }

                if ($filters['from'] ?? null) {
                    $returnQuery->whereDate('received_date', '>=', $filters['from']);
                }

                if ($filters['to'] ?? null) {
                    $returnQuery->whereDate('received_date', '<=', $filters['to']);
                }
            });

        if ($filters['connection_id'] ?? null) {
            if ((int) $filters['connection_id'] !== $this->defaultConnectionId()) {
                return ReturnItem::query()->whereRaw('0 = 1');
            }
        }

        return $query;
    }

    protected function defaultConnectionId(): int
    {
        $connection = Connection::query()->where('is_active', true)->first()
            ?? Connection::query()->first();

        return (int) ($connection?->id ?? 0);
    }

    protected function storeLabel(Connection $connection): string
    {
        if (! filled($connection->store_url)) {
            return 'Store #'.$connection->id;
        }

        $host = parse_url($connection->store_url, PHP_URL_HOST);

        return $host ?: $connection->store_url;
    }
}
