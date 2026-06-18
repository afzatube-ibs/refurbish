<?php

namespace App\Enums;

enum SettlementBatchDirection: string
{
    case SupplierPaymentCompleted = 'supplier_payment_completed';
    case SupplierCollectionCompleted = 'supplier_collection_completed';

    public function label(): string
    {
        return match ($this) {
            self::SupplierPaymentCompleted => 'Lokkisona paid supplier',
            self::SupplierCollectionCompleted => 'Supplier paid Lokkisona',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::SupplierPaymentCompleted => 'Payment to supplier',
            self::SupplierCollectionCompleted => 'Collection from supplier',
        };
    }
}
