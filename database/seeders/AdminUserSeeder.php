<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@lokkisona.com'],
            [
                'name' => 'Lokkisona Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'supplier_id' => null,
                'is_active' => true,
            ],
        );
    }
}
