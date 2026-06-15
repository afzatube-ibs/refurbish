<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('admin')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->string('store_url');
            $table->text('api_token');
            $table->string('product_api_endpoint');
            $table->string('order_api_endpoint');
            $table->string('order_status_api_endpoint');
            $table->string('supplier_filter')->default('ex-a');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('product_sync_page')->default(1);
            $table->timestamp('last_product_sync_at')->nullable();
            $table->timestamp('last_order_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('source_product_id');
            $table->string('image')->nullable();
            $table->string('model');
            $table->string('name');
            $table->string('type')->nullable();
            $table->integer('stock')->default(0);
            $table->decimal('supplier_cost', 12, 2)->default(0);
            $table->string('supplier_model')->nullable();
            $table->integer('supplier_stock')->nullable();
            $table->integer('low_warning')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'source_product_id'], 'sp_supplier_source_product_uq');
        });

        Schema::create('supplier_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->string('source_variant_key');
            $table->string('option_label');
            $table->string('option_image')->nullable();
            $table->integer('stock')->default(0);
            $table->timestamps();
            $table->unique(['supplier_product_id', 'source_variant_key'], 'spv_product_variant_uq');
        });

        Schema::create('order_status_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('source_status_id')->unique();
            $table->string('source_status_name');
            $table->string('sfm_status')->default('ignore');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('source_order_id')->unique();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('customer_address');
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->string('current_oc_status');
            $table->string('sfm_status')->default('new');
            $table->string('courier_status')->nullable();
            $table->string('consignment_id')->nullable();
            $table->string('courier_name')->nullable();
            $table->timestamp('oc_created_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_product_id');
            $table->string('product_name');
            $table->string('model');
            $table->string('variant_label')->nullable();
            $table->integer('quantity');
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('supplier_product_cost_snapshot', 12, 2)->nullable();
            $table->timestamp('cost_snapshotted_at')->nullable();
            $table->string('item_status')->default('active');
            $table->timestamps();
        });

        Schema::create('dispatch_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('dispatch_date');
            $table->string('courier');
            $table->string('consignment_id');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('dispatch_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('supplier_cost_snapshot', 12, 2);
            $table->timestamps();
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('return_status')->default('pending');
            $table->date('received_date')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('supplier_cost_snapshot', 12, 2);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('dispatch_report_items');
        Schema::dropIfExists('dispatch_reports');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_status_mappings');
        Schema::dropIfExists('supplier_product_variants');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('connections');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['role', 'is_active']);
        });
        Schema::dropIfExists('suppliers');
    }
};
