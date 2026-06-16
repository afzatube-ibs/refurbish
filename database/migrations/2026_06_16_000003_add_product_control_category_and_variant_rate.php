<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_control_states', function (Blueprint $table) {
            $table->string('product_category')->nullable()->after('sm_model');
        });

        Schema::table('product_control_variants', function (Blueprint $table) {
            $table->decimal('rate', 12, 2)->nullable()->after('sm_model');
        });
    }

    public function down(): void
    {
        Schema::table('product_control_variants', function (Blueprint $table) {
            $table->dropColumn('rate');
        });

        Schema::table('product_control_states', function (Blueprint $table) {
            $table->dropColumn('product_category');
        });
    }
};
