<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['kazoo_extensions', 'kazoo_devices', 'kazoo_voicemail_boxes', 'kazoo_callflows'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->json('source_payload')->nullable()->after('projection_version');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['kazoo_extensions', 'kazoo_devices', 'kazoo_voicemail_boxes', 'kazoo_callflows'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('source_payload');
            });
        }
    }
};
