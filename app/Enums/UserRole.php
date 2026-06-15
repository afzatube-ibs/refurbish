<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Supplier => 'Supplier',
        };
    }
}
