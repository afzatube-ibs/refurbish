<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('source_snapshot')->nullable()->after('oc_created_at');
            $table->boolean('stock_deducted')->default(false)->after('source_snapshot');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('option_name')->nullable()->after('variant_label');
            $table->string('option_value')->nullable()->after('option_name');
            $table->boolean('is_unmatched')->default(false)->after('option_value');
            $table->string('source_variant_key')->nullable()->after('is_unmatched');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['source_snapshot', 'stock_deducted']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['option_name', 'option_value', 'is_unmatched', 'source_variant_key']);
        });
    }
};
