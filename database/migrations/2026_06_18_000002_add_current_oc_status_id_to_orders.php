<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('current_oc_status_id')->nullable()->after('current_oc_status');
        });

        DB::table('orders')->orderBy('id')->get()->each(function (object $row) {
            $snapshot = json_decode((string) ($row->source_snapshot ?? ''), true);

            if (! is_array($snapshot)) {
                return;
            }

            $statusId = (int) ($snapshot['current_oc_status_id'] ?? $snapshot['order_status_id'] ?? 0);

            if ($statusId > 0) {
                DB::table('orders')->where('id', $row->id)->update([
                    'current_oc_status_id' => $statusId,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('current_oc_status_id');
        });
    }
};
