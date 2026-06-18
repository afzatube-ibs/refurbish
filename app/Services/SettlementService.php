<?php

namespace App\Services;

use App\Enums\SettlementEntryType;
use App\Models\Connection;
use App\Models\SettlementEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function __construct(
        protected SupplierLedgerService $ledgerService,
        protected ActivityLogService $activityLog,
    ) {}

    public function record(
        int $supplierId,
        SettlementEntryType $entryType,
        float $amount,
        \DateTimeInterface $entryDate,
        User $recordedBy,
        ?string $reference = null,
        ?string $notes = null,
        ?int $orderId = null,
        ?int $connectionId = null,
        ?string $collectionSource = null,
    ): SettlementEntry {
        return DB::transaction(function () use (
            $supplierId,
            $entryType,
            $amount,
            $entryDate,
            $recordedBy,
            $reference,
            $notes,
            $orderId,
            $connectionId,
            $collectionSource,
        ) {
            $entry = SettlementEntry::query()->create([
                'supplier_id' => $supplierId,
                'connection_id' => $connectionId ?? $this->activeConnectionId(),
                'order_id' => $orderId,
                'entry_type' => $entryType,
                'collection_source' => $collectionSource,
                'amount' => round($amount, 2),
                'entry_date' => $entryDate,
                'reference' => $reference,
                'notes' => $notes,
                'recorded_by' => $recordedBy->id,
            ]);

            $this->ledgerService->postSettlement($entry);

            $this->activityLog->log('settlement.recorded', SettlementEntry::class, $entry->id, [
                'supplier_id' => $supplierId,
                'entry_type' => $entryType->value,
                'amount' => $amount,
            ]);

            return $entry->fresh(['recordedBy', 'ledgerEntry']);
        });
    }

    protected function activeConnectionId(): ?int
    {
        $connection = Connection::query()->where('is_active', true)->first()
            ?? Connection::query()->first();

        return $connection?->id;
    }
}
