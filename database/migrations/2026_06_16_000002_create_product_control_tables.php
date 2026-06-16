<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_control_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('source_product_id');
            $table->string('ibs_model')->nullable();
            $table->string('sm_model')->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->integer('low_warning')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'source_product_id'], 'pcs_supplier_product_uq');
        });

        Schema::create('product_control_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_control_state_id')->constrained()->cascadeOnDelete();
            $table->string('source_variant_key');
            $table->string('ibs_model')->nullable();
            $table->string('sm_model')->nullable();
            $table->integer('ibs_stock')->nullable();
            $table->integer('low_warning')->nullable();
            $table->timestamps();

            $table->unique(['product_control_state_id', 'source_variant_key'], 'pcv_state_variant_uq');
        });

        Schema::create('product_rate_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('product_id');
            $table->string('variant_id')->nullable();
            $table->decimal('old_rate', 12, 2)->nullable();
            $table->decimal('new_rate', 12, 2);
            $table->decimal('difference', 12, 2);
            $table->dateTime('effective_from');
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_id', 'product_id', 'effective_from'], 'prh_supplier_product_effective_idx');
        });

        Schema::create('stock_adjustment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('product_id');
            $table->string('variant_id')->nullable();
            $table->integer('old_stock')->nullable();
            $table->integer('new_stock');
            $table->integer('difference');
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_id', 'product_id', 'created_at'], 'sah_supplier_product_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_history');
        Schema::dropIfExists('product_rate_history');
        Schema::dropIfExists('product_control_variants');
        Schema::dropIfExists('product_control_states');
    }
};
