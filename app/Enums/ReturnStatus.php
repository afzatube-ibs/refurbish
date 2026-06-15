<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Return Pending',
            self::Confirmed => 'Return Received Confirmed',
        };
    }
}
