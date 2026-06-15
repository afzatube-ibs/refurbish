<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'extension/dropflow/products' => 'api/ibs/products',
            'extension/dropflow/orders' => 'api/ibs/orders',
            'extension/dropflow/order_statuses' => 'api/ibs/order_queue_statuses',
        ];

        $connections = DB::table('connections')->get(['id', 'product_api_endpoint', 'order_api_endpoint', 'order_status_api_endpoint']);

        foreach ($connections as $connection) {
            $updates = [];

            foreach ([
                'product_api_endpoint' => $connection->product_api_endpoint,
                'order_api_endpoint' => $connection->order_api_endpoint,
                'order_status_api_endpoint' => $connection->order_status_api_endpoint,
            ] as $column => $value) {
                foreach ($map as $from => $to) {
                    if (is_string($value) && str_contains($value, $from)) {
                        $updates[$column] = str_replace($from, $to, $value);
                        break;
                    }
                }
            }

            if ($updates !== []) {
                DB::table('connections')->where('id', $connection->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive endpoint migration; no rollback.
    }
};
