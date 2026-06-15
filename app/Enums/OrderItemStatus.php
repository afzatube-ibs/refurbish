<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Active = 'active';
    case ReturnPending = 'return_pending';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::ReturnPending => 'Return Pending',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }
}
