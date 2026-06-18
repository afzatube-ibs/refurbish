<?php

namespace App\Enums;

enum CollectionSource: string
{
    case Cod = 'cod';
    case Courier = 'courier';
    case Cash = 'cash';
    case Bank = 'bank';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'COD',
            self::Courier => 'Courier',
            self::Cash => 'Cash',
            self::Bank => 'Bank',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Other => 'Other',
        };
    }
}
