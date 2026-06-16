<?php

use App\Enums\OrderSyncRole;
use App\Enums\SfmOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_mappings', function (Blueprint $table) {
            $table->string('sync_role')->default(OrderSyncRole::Ignore->value)->after('sfm_status');
        });

        DB::table('order_status_mappings')->orderBy('id')->get()->each(function (object $row) {
            $status = SfmOrderStatus::tryFrom((string) $row->sfm_status) ?? SfmOrderStatus::Ignore;
            $role = ! ($row->oc_selected ?? false)
                ? OrderSyncRole::Ignore
                : OrderSyncRole::recommendedFor($status);

            DB::table('order_status_mappings')
                ->where('id', $row->id)
                ->update(['sync_role' => $role->value]);
        });
    }

    public function down(): void
    {
        Schema::table('order_status_mappings', function (Blueprint $table) {
            $table->dropColumn('sync_role');
        });
    }
};
