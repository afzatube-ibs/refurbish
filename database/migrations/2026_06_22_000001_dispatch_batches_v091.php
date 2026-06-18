<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->date('dispatch_date');
            $table->string('status')->default('finalized');
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('total_qty')->default(0);
            $table->decimal('total_supplier_cost', 12, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dispatch_batch_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('order_no');
            $table->string('customer_name');
            $table->string('phone');
            $table->string('courier')->nullable();
            $table->string('consignment_id');
            $table->unsignedInteger('total_qty')->default(0);
            $table->decimal('total_supplier_cost', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('dispatch_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('model');
            $table->string('ibs_model')->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->decimal('supplier_unit_cost', 12, 2)->default(0);
            $table->decimal('supplier_total_cost', 12, 2)->default(0);
            $table->string('cost_status')->default('ok');
            $table->timestamps();
        });

        Schema::table('dispatch_reports', function (Blueprint $table) {
            $table->foreignId('dispatch_batch_id')
                ->nullable()
                ->after('created_by')
                ->constrained('dispatch_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispatch_batch_id');
        });

        Schema::dropIfExists('dispatch_batch_items');
        Schema::dropIfExists('dispatch_batch_orders');
        Schema::dropIfExists('dispatch_batches');
    }
};
