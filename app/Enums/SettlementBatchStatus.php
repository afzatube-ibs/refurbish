<?php

namespace App\Enums;

enum SettlementBatchStatus: string
{
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Closed => 'Closed',
        };
    }
}
