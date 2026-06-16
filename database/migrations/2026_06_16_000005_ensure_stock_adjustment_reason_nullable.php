<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_adjustment_history')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => DB::statement(
                'ALTER TABLE stock_adjustment_history MODIFY reason VARCHAR(255) NULL'
            ),
            'pgsql' => DB::statement(
                'ALTER TABLE stock_adjustment_history ALTER COLUMN reason DROP NOT NULL'
            ),
            default => null,
        };
    }

    public function down(): void
    {
        // Intentionally left empty — nullable reason is required for initial stock setup.
    }
};
