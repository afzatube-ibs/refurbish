<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type');
            $table->decimal('amount', 12, 2);
            $table->date('entry_date');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('supplier_ledger_entries', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
            $table->foreignId('connection_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('settlement_entry_id')->nullable()->after('connection_id')->constrained()->nullOnDelete();
            $table->string('source_type')->nullable()->after('settlement_entry_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->unique(['source_type', 'source_id'], 'supplier_ledger_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_ledger_entries', function (Blueprint $table) {
            $table->dropUnique('supplier_ledger_source_unique');
            $table->dropConstrainedForeignId('settlement_entry_id');
            $table->dropConstrainedForeignId('connection_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['source_type', 'source_id']);
        });

        Schema::dropIfExists('settlement_entries');
    }
};
