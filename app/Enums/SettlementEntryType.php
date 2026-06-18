<?php

namespace App\Enums;

enum SettlementEntryType: string
{
    case PaidToStoreOwner = 'paid_to_store_owner';
    case ReceivedFromSupplier = 'received_from_supplier';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::PaidToStoreOwner => 'Paid to store owner',
            self::ReceivedFromSupplier => 'Received from supplier',
            self::Adjustment => 'Adjustment',
        };
    }

    public function ledgerType(): LedgerEntryType
    {
        return match ($this) {
            self::PaidToStoreOwner => LedgerEntryType::PaidToStoreOwner,
            self::ReceivedFromSupplier => LedgerEntryType::ReceivedFromSupplier,
            self::Adjustment => LedgerEntryType::Adjustment,
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::PaidToStoreOwner => 'Money received by store owner from supplier or COD collection.',
            self::ReceivedFromSupplier => 'Supplier paid or returned money to store owner.',
            self::Adjustment => 'Manual correction — amount may be positive or negative.',
        };
    }
}
