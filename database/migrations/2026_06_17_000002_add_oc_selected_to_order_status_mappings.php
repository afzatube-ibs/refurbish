<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_mappings', function (Blueprint $table) {
            $table->boolean('oc_selected')->default(false)->after('source_status_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_status_mappings', function (Blueprint $table) {
            $table->dropColumn('oc_selected');
        });
    }
};
