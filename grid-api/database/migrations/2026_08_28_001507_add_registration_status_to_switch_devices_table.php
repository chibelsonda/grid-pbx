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
        Schema::table('switch_devices', function (Blueprint $table) {
            $table->string('registration_status', 32)
                ->default('unknown')
                ->after('is_enabled');
            $table->timestamp('registration_checked_at')
                ->nullable()
                ->after('registration_status');
            $table->index(
                ['switch_account_id', 'registration_status'],
                'switch_devices_account_registration_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('switch_devices', function (Blueprint $table) {
            $table->dropIndex('switch_devices_account_registration_index');
            $table->dropColumn(['registration_status', 'registration_checked_at']);
        });
    }
};
