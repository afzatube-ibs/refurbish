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

    public function operationalLabel(): string
    {
        return match ($this) {
            self::PaidToStoreOwner => 'Received by Supplier',
            self::ReceivedFromSupplier => 'Payment to Dropshipper',
            self::Adjustment => 'Adjustment',
        };
    }

    public function operationalHelpText(): string
    {
        return match ($this) {
            self::PaidToStoreOwner => 'Supplier received COD, courier, or customer money.',
            self::ReceivedFromSupplier => 'Supplier paid or transferred money to Lokkisona / store owner.',
            self::Adjustment => 'Manual correction — amount may be positive or negative.',
        };
    }

    public static function fromOperationalKey(string $key): self
    {
        return match ($key) {
            'received_by_supplier' => self::PaidToStoreOwner,
            'payment_to_dropshipper' => self::ReceivedFromSupplier,
            'adjustment' => self::Adjustment,
            default => throw new \InvalidArgumentException("Unknown operational entry type: {$key}"),
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
