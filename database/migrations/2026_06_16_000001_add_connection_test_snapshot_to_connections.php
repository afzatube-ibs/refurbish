<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->timestamp('last_connection_test_at')->nullable()->after('last_order_sync_at');
            $table->string('last_connection_test_status')->nullable()->after('last_connection_test_at');
            $table->text('last_connection_test_message')->nullable()->after('last_connection_test_status');
            $table->string('last_connector_version')->nullable()->after('last_connection_test_message');
            $table->string('last_option_image_summary')->nullable()->after('last_connector_version');
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->dropColumn([
                'last_connection_test_at',
                'last_connection_test_status',
                'last_connection_test_message',
                'last_connector_version',
                'last_option_image_summary',
            ]);
        });
    }
};
