<?php

namespace App\Enums;

enum SfmOrderStatus: string
{
    case Ignore = 'ignore';
    case New = 'new';
    case Accepted = 'accepted';
    case Packed = 'packed';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Hold = 'hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Ignore => 'Ignore',
            self::New => 'New',
            self::Accepted => 'Accepted',
            self::Packed => 'Packed',
            self::Dispatched => 'Dispatched',
            self::Delivered => 'Delivered',
            self::Returned => 'Returned',
            self::Hold => 'Hold',
            self::Cancelled => 'Cancelled',
        };
    }

    public function rank(): int
    {
        return config('dropflow.status_ranks.'.$this->value, 0);
    }

    public static function mappableCases(): array
    {
        return array_filter(self::cases(), fn (self $s) => $s !== self::Ignore || true);
    }

    public static function forMappingDropdown(): array
    {
        return self::cases();
    }
}
