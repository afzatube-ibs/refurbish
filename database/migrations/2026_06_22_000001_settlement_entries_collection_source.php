<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_entries', function (Blueprint $table) {
            $table->string('collection_source')->nullable()->after('entry_type');
        });
    }

    public function down(): void
    {
        Schema::table('settlement_entries', function (Blueprint $table) {
            $table->dropColumn('collection_source');
        });
    }
};
