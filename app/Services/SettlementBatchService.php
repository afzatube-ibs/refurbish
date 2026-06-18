<?php

namespace App\Services;

use App\Enums\SettlementBatchDirection;
use App\Enums\SettlementBatchStatus;
use App\Models\Connection;
use App\Models\SettlementBatch;
use App\Models\SettlementEntry;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SettlementBatchService
{
    public function __construct(
        protected PayableService $payableService,
        protected ActivityLogService $activityLog,
    ) {}

    public function close(
        int $supplierId,
        User $closedBy,
        ?int $connectionId = null,
        ?string $notes = null,
    ): SettlementBatch {
        $connectionId = $connectionId ?? $this->activeConnectionId();

        $ledgerQuery = SupplierLedgerEntry::query()
            ->where('supplier_id', $supplierId)
            ->whereNull('settlement_batch_id');

        if ($connectionId) {
            $ledgerQuery->where('connection_id', $connectionId);
        }

        $ledgerEntries = $ledgerQuery->orderBy('entry_date')->orderBy('id')->get();

        if ($ledgerEntries->isEmpty()) {
            throw new InvalidArgumentException('No open ledger entries to close.');
        }

        $closingBalance = round((float) $ledgerEntries->sum(fn ($entry) => (float) $entry->amount), 2);

        if ($closingBalance == 0.0) {
            throw new InvalidArgumentException('Current balance is already settled.');
        }

        $direction = $closingBalance < 0
            ? SettlementBatchDirection::SupplierPaymentCompleted
            : SettlementBatchDirection::SupplierCollectionCompleted;

        return DB::transaction(function () use (
            $supplierId,
            $connectionId,
            $closingBalance,
            $direction,
            $closedBy,
            $notes,
            $ledgerEntries,
        ) {
            $batch = SettlementBatch::query()->create([
                'batch_no' => $this->nextBatchNo(),
                'supplier_id' => $supplierId,
                'connection_id' => $connectionId,
                'opening_balance' => 0,
                'closing_balance' => $closingBalance,
                'direction' => $direction,
                'status' => SettlementBatchStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $closedBy->id,
                'notes' => $notes,
            ]);

            SupplierLedgerEntry::query()
                ->whereIn('id', $ledgerEntries->pluck('id'))
                ->update(['settlement_batch_id' => $batch->id]);

            SettlementEntry::query()
                ->where('supplier_id', $supplierId)
                ->whereNull('settlement_batch_id')
                ->when($connectionId, fn ($query) => $query->where('connection_id', $connectionId))
                ->update(['settlement_batch_id' => $batch->id]);

            $this->activityLog->log('settlement.batch.closed', SettlementBatch::class, $batch->id, [
                'supplier_id' => $supplierId,
                'connection_id' => $connectionId,
                'closing_balance' => $closingBalance,
                'direction' => $direction->value,
            ]);

            return $batch->fresh(['supplier', 'connection', 'closedBy']);
        });
    }

    /**
     * @return array{
     *     batch: SettlementBatch,
     *     transactions: \Illuminate\Support\Collection<int, SupplierLedgerEntry>,
     *     who_paid_whom: string
     * }
     */
    public function detail(SettlementBatch $batch): array
    {
        $batch->load(['supplier', 'connection', 'closedBy']);

        $transactions = $batch->ledgerEntries()
            ->with(['order', 'settlementEntry'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        return [
            'batch' => $batch,
            'transactions' => $transactions,
            'who_paid_whom' => $batch->direction->label(),
        ];
    }

    protected function nextBatchNo(): string
    {
        $year = now()->format('Y');
        $prefix = 'SB-'.$year.'-';
        $latest = SettlementBatch::query()
            ->where('batch_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('batch_no');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    protected function activeConnectionId(): ?int
    {
        $connection = Connection::query()->where('is_active', true)->first()
            ?? Connection::query()->first();

        return $connection?->id;
    }
}
