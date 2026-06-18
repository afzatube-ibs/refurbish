<?php

namespace App\Enums;

enum DispatchBatchStatus: string
{
    case Dispatched = 'dispatched';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Dispatched => 'Dispatched',
            self::Finalized => 'Finalized',
        };
    }
}
