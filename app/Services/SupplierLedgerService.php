<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\SettlementEntryType;
use App\Models\Connection;
use App\Models\DispatchReport;
use App\Models\ReturnModel;
use App\Models\SettlementEntry;
use App\Models\SupplierLedgerEntry;
use Illuminate\Support\Facades\DB;

class SupplierLedgerService
{
    public function signedAmount(LedgerEntryType $type, float $amount): float
    {
        $amount = round($amount, 2);

        return match ($type) {
            LedgerEntryType::DispatchCost,
            LedgerEntryType::PaidToStoreOwner => -abs($amount),
            LedgerEntryType::ReturnReversal,
            LedgerEntryType::ReceivedFromSupplier => abs($amount),
            LedgerEntryType::Adjustment => $amount,
        };
    }

    public function postDispatch(DispatchReport $report): SupplierLedgerEntry
    {
        $report->loadMissing('items');

        $total = (float) $report->items->sum(
            fn ($item) => $item->quantity * $item->supplier_cost_snapshot
        );

        return $this->postUnique(
            DispatchReport::class,
            $report->id,
            [
                'supplier_id' => $report->supplier_id,
                'order_id' => $report->order_id,
                'connection_id' => $this->activeConnectionId(),
                'entry_date' => $report->dispatch_date,
                'type' => LedgerEntryType::DispatchCost->value,
                'amount' => $this->signedAmount(LedgerEntryType::DispatchCost, $total),
                'reference' => 'Dispatch #'.$report->id,
                'notes' => trim(($report->courier ?? '').' '.$report->consignment_id) ?: null,
            ]
        );
    }

    public function postReturnReversal(ReturnModel $return): SupplierLedgerEntry
    {
        $return->loadMissing('returnItems');

        $total = (float) $return->returnItems->sum(
            fn ($item) => $item->quantity * $item->supplier_cost_snapshot
        );

        return $this->postUnique(
            ReturnModel::class,
            $return->id,
            [
                'supplier_id' => $return->supplier_id,
                'order_id' => $return->order_id,
                'connection_id' => $this->activeConnectionId(),
                'entry_date' => $return->received_date ?? $return->created_at,
                'type' => LedgerEntryType::ReturnReversal->value,
                'amount' => $this->signedAmount(LedgerEntryType::ReturnReversal, $total),
                'reference' => 'Return #'.$return->id,
                'notes' => 'Confirmed return reversal',
            ]
        );
    }

    public function postSettlement(SettlementEntry $entry): SupplierLedgerEntry
    {
        $ledgerType = $entry->entry_type->ledgerType();
        $signed = $entry->entry_type === SettlementEntryType::Adjustment
            ? (float) $entry->amount
            : $this->signedAmount($ledgerType, (float) $entry->amount);

        return $this->postUnique(
            SettlementEntry::class,
            $entry->id,
            [
                'supplier_id' => $entry->supplier_id,
                'order_id' => $entry->order_id,
                'connection_id' => $entry->connection_id,
                'settlement_entry_id' => $entry->id,
                'entry_date' => $entry->entry_date,
                'type' => $ledgerType->value,
                'amount' => round($signed, 2),
                'reference' => $entry->reference,
                'notes' => $entry->notes,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function orderHistory(int $orderId): array
    {
        return SupplierLedgerEntry::query()
            ->where('order_id', $orderId)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get()
            ->map(function (SupplierLedgerEntry $entry) {
                $type = $entry->type instanceof LedgerEntryType
                    ? $entry->type
                    : LedgerEntryType::tryFrom((string) $entry->type);

                return [
                    'date' => $entry->entry_date->format('Y-m-d'),
                    'type' => $type?->value ?? (string) $entry->type,
                    'type_label' => $type?->label() ?? (string) $entry->type,
                    'amount' => (float) $entry->amount,
                    'reference' => $entry->reference,
                    'notes' => $entry->notes,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function postUnique(string $sourceType, int $sourceId, array $attributes): SupplierLedgerEntry
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $attributes) {
            $existing = SupplierLedgerEntry::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing) {
                return $existing;
            }

            return SupplierLedgerEntry::query()->create(array_merge($attributes, [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]));
        });
    }

    protected function activeConnectionId(): ?int
    {
        $connection = Connection::query()->where('is_active', true)->first()
            ?? Connection::query()->first();

        return $connection?->id;
    }
}
