<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\ReturnStatus;
use App\Enums\SettlementEntryType;
use App\Models\DispatchReport;
use App\Models\DispatchReportItem;
use App\Models\ReturnItem;
use App\Models\ReturnModel;
use App\Models\SettlementEntry;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayableService
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected SettlementService $settlementService,
        protected SupplierLedgerService $ledgerService,
    ) {}

    /**
     * @param  array{from?: string, to?: string}|null  $dateRange
     * @return array{
     *     delivered_cost: float,
     *     returned_cost: float,
     *     paid_to_store_owner: float,
     *     received_from_supplier: float,
     *     total_paid: float,
     *     adjustment_total: float,
     *     received_from_supplier: float,
     *     net_payable: float
     * }
     */
    public function summary(?int $supplierId, ?array $dateRange = null, ?int $connectionId = null): array
    {
        if ($this->hasLedgerData($supplierId, $connectionId)) {
            return $this->summaryFromLedger($supplierId, $dateRange, $connectionId);
        }

        return $this->summaryFromOperational($supplierId, $dateRange);
    }

    public function recordPayment(
        int $supplierId,
        float $amount,
        \DateTimeInterface $paymentDate,
        User $recordedBy,
        ?string $reference = null,
        ?string $notes = null
    ): SettlementEntry {
        return $this->settlementService->record(
            $supplierId,
            SettlementEntryType::ReceivedFromSupplier,
            $amount,
            $paymentDate,
            $recordedBy,
            $reference,
            $notes,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accountStatement(
        ?int $supplierId,
        ?array $dateRange = null,
        ?int $connectionId = null,
    ): array {
        $query = SupplierLedgerEntry::query()
            ->with(['supplier', 'connection', 'order'])
            ->orderBy('entry_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        if ($dateRange['from'] ?? null) {
            $query->whereDate('entry_date', '>=', $dateRange['from']);
        }

        if ($dateRange['to'] ?? null) {
            $query->whereDate('entry_date', '<=', $dateRange['to']);
        }

        $running = 0.0;
        $rows = [];

        foreach ($query->get() as $entry) {
            $running += (float) $entry->amount;
            $type = $entry->type instanceof LedgerEntryType ? $entry->type : LedgerEntryType::tryFrom((string) $entry->type);

            $rows[] = [
                'entry' => $entry,
                'type_label' => $type?->label() ?? (string) $entry->type,
                'running_balance' => round($running, 2),
            ];
        }

        return array_reverse($rows);
    }

    protected function hasLedgerData(?int $supplierId, ?int $connectionId): bool
    {
        $query = SupplierLedgerEntry::query();

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        return $query->exists();
    }

    /**
     * @param  array{from?: string, to?: string}|null  $dateRange
     * @return array<string, float>
     */
    protected function summaryFromLedger(?int $supplierId, ?array $dateRange, ?int $connectionId): array
    {
        $query = SupplierLedgerEntry::query();

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        if ($dateRange['from'] ?? null) {
            $query->whereDate('entry_date', '>=', $dateRange['from']);
        }

        if ($dateRange['to'] ?? null) {
            $query->whereDate('entry_date', '<=', $dateRange['to']);
        }

        $entries = $query->get();

        $delivered = $entries
            ->filter(fn ($entry) => $this->entryTypeValue($entry) === LedgerEntryType::DispatchCost->value)
            ->sum(fn ($entry) => abs((float) $entry->amount));

        $returned = $entries
            ->filter(fn ($entry) => $this->entryTypeValue($entry) === LedgerEntryType::ReturnReversal->value)
            ->sum(fn ($entry) => abs((float) $entry->amount));

        $paidToStore = $entries
            ->filter(fn ($entry) => $this->entryTypeValue($entry) === LedgerEntryType::PaidToStoreOwner->value)
            ->sum(fn ($entry) => abs((float) $entry->amount));

        $received = $entries
            ->filter(fn ($entry) => $this->entryTypeValue($entry) === LedgerEntryType::ReceivedFromSupplier->value)
            ->sum(fn ($entry) => abs((float) $entry->amount));

        $adjustment = $entries
            ->filter(fn ($entry) => $this->entryTypeValue($entry) === LedgerEntryType::Adjustment->value)
            ->sum(fn ($entry) => (float) $entry->amount);

        $netPayable = round((float) $entries->sum('amount'), 2);

        return [
            'delivered_cost' => round($delivered, 2),
            'returned_cost' => round($returned, 2),
            'paid_to_store_owner' => round($paidToStore, 2),
            'received_from_supplier' => round($received, 2),
            'total_paid' => round($paidToStore + $received, 2),
            'adjustment_total' => round($adjustment, 2),
            'net_payable' => $netPayable,
        ];
    }

    /**
     * @param  array{from?: string, to?: string}|null  $dateRange
     * @return array<string, float>
     */
    protected function summaryFromOperational(?int $supplierId, ?array $dateRange): array
    {
        $deliveredQuery = DispatchReportItem::query()
            ->whereHas('dispatchReport', function ($query) use ($supplierId, $dateRange) {
                if ($supplierId) {
                    $query->where('supplier_id', $supplierId);
                }

                if ($dateRange['from'] ?? null) {
                    $query->whereDate('dispatch_date', '>=', $dateRange['from']);
                }

                if ($dateRange['to'] ?? null) {
                    $query->whereDate('dispatch_date', '<=', $dateRange['to']);
                }
            });

        $deliveredCost = (float) (clone $deliveredQuery)
            ->selectRaw('COALESCE(SUM(quantity * supplier_cost_snapshot), 0) as total')
            ->value('total');

        $returnedQuery = ReturnItem::query()
            ->whereHas('returnRecord', function ($query) use ($supplierId, $dateRange) {
                if ($supplierId) {
                    $query->where('supplier_id', $supplierId);
                }

                $query->where('return_status', ReturnStatus::Confirmed);

                if ($dateRange['from'] ?? null) {
                    $query->whereDate('received_date', '>=', $dateRange['from']);
                }

                if ($dateRange['to'] ?? null) {
                    $query->whereDate('received_date', '<=', $dateRange['to']);
                }
            });

        $returnedCost = (float) (clone $returnedQuery)
            ->selectRaw('COALESCE(SUM(quantity * supplier_cost_snapshot), 0) as total')
            ->value('total');

        $paymentsQuery = SupplierPayment::query();

        if ($supplierId) {
            $paymentsQuery->where('supplier_id', $supplierId);
        }

        if ($dateRange['from'] ?? null) {
            $paymentsQuery->whereDate('payment_date', '>=', $dateRange['from']);
        }

        if ($dateRange['to'] ?? null) {
            $paymentsQuery->whereDate('payment_date', '<=', $dateRange['to']);
        }

        $receivedFromSupplier = (float) $paymentsQuery->sum('amount');
        $netPayable = $deliveredCost - $returnedCost - $receivedFromSupplier;

        return [
            'delivered_cost' => round($deliveredCost, 2),
            'returned_cost' => round($returnedCost, 2),
            'paid_to_store_owner' => 0.0,
            'received_from_supplier' => round($receivedFromSupplier, 2),
            'total_paid' => round($receivedFromSupplier, 2),
            'adjustment_total' => 0.0,
            'net_payable' => round($netPayable, 2),
        ];
    }

    protected function entryTypeValue(SupplierLedgerEntry $entry): string
    {
        return $entry->type instanceof LedgerEntryType ? $entry->type->value : (string) $entry->type;
    }
}
