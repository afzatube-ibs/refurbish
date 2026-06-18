<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case DispatchCost = 'dispatch_cost';
    case ReturnReversal = 'return_reversal';
    case PaidToStoreOwner = 'paid_to_store_owner';
    case ReceivedFromSupplier = 'received_from_supplier';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::DispatchCost => 'Dispatch cost',
            self::ReturnReversal => 'Return reversal',
            self::PaidToStoreOwner => 'Paid to store owner',
            self::ReceivedFromSupplier => 'Received from supplier',
            self::Adjustment => 'Adjustment',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::DispatchCost => false,
            self::ReturnReversal,
            self::PaidToStoreOwner,
            self::ReceivedFromSupplier => true,
            self::Adjustment => false,
        };
    }
}
