<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2);
            $table->string('direction');
            $table->string('status')->default('closed');
            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('settlement_entries', function (Blueprint $table) {
            $table->foreignId('settlement_batch_id')
                ->nullable()
                ->after('recorded_by')
                ->constrained('settlement_batches')
                ->nullOnDelete();
        });

        Schema::table('supplier_ledger_entries', function (Blueprint $table) {
            $table->foreignId('settlement_batch_id')
                ->nullable()
                ->after('settlement_entry_id')
                ->constrained('settlement_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_batch_id');
        });

        Schema::table('settlement_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_batch_id');
        });

        Schema::dropIfExists('settlement_batches');
    }
};
