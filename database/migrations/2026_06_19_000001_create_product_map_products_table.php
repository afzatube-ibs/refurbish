<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_map_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('source_product_id');
            $table->json('oc_snapshot');
            $table->json('source_product_snapshot')->nullable();
            $table->string('oc_fingerprint', 64);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'source_product_id'], 'pmp_supplier_product_uq');
            $table->index(['supplier_id', 'last_synced_at'], 'pmp_supplier_synced_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_map_products');
    }
};
