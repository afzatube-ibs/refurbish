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
            'sqlite' => $this->makeReasonNullableSqlite(),
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_adjustment_history')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => DB::statement(
                'ALTER TABLE stock_adjustment_history MODIFY reason VARCHAR(255) NOT NULL'
            ),
            'pgsql' => DB::statement(
                'ALTER TABLE stock_adjustment_history ALTER COLUMN reason SET NOT NULL'
            ),
            default => null,
        };
    }

    protected function makeReasonNullableSqlite(): void
    {
        $columns = collect(DB::select('PRAGMA table_info(stock_adjustment_history)'));
        $reasonColumn = $columns->firstWhere('name', 'reason');

        if ($reasonColumn !== null && (int) ($reasonColumn->notnull ?? 1) === 0) {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        DB::statement(<<<'SQL'
CREATE TABLE stock_adjustment_history_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    supplier_id INTEGER NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    variant_id VARCHAR(255) NULL,
    old_stock INTEGER NULL,
    new_stock INTEGER NOT NULL,
    difference INTEGER NOT NULL,
    reason VARCHAR(255) NULL,
    note TEXT NULL,
    changed_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
)
SQL);

        DB::statement(<<<'SQL'
INSERT INTO stock_adjustment_history_new (
    id, supplier_id, product_id, variant_id, old_stock, new_stock, difference, reason, note, changed_by, created_at
)
SELECT
    id, supplier_id, product_id, variant_id, old_stock, new_stock, difference, reason, note, changed_by, created_at
FROM stock_adjustment_history
SQL);

        DB::statement('DROP TABLE stock_adjustment_history');
        DB::statement('ALTER TABLE stock_adjustment_history_new RENAME TO stock_adjustment_history');
        DB::statement('CREATE INDEX sah_supplier_product_created_idx ON stock_adjustment_history (supplier_id, product_id, created_at)');

        DB::statement('PRAGMA foreign_keys=ON');
    }
};
