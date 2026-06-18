<?php

namespace App\Enums;

enum DispatchBatchItemCostStatus: string
{
    case Ok = 'ok';
    case MissingCost = 'missing_cost';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::MissingCost => 'Missing cost',
        };
    }
}
