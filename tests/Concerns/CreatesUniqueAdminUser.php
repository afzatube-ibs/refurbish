<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait CreatesUniqueAdminUser
{
    protected function adminUser(string $prefix = 'product-map-admin'): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => $prefix.'-'.uniqid('', true).'@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }
}
