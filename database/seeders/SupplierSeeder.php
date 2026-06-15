<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = Supplier::updateOrCreate(
            ['code' => 'EXA'],
            [
                'name' => 'Ex-A',
                'contact_name' => 'Ex-A Contact',
                'contact_email' => 'supplier@ex-a.com',
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'supplier@ex-a.com'],
            [
                'name' => 'Ex-A Supplier',
                'password' => Hash::make('password'),
                'role' => UserRole::Supplier,
                'supplier_id' => $supplier->id,
                'is_active' => true,
            ],
        );
    }
}
