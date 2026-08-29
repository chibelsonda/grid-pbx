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
        Schema::table('switch_devices', function (Blueprint $table): void {
            $table->string('provisioning_enrollment_status', 32)
                ->default('not_enrolled')
                ->after('active_mac_identity');
            $table->string('provisioning_enrollment_provider', 128)
                ->nullable()
                ->after('provisioning_enrollment_status');
            $table->timestamp('provisioning_enrolled_at')
                ->nullable()
                ->after('provisioning_enrollment_provider');
            $table->timestamp('provisioning_detached_at')
                ->nullable()
                ->after('provisioning_enrolled_at');
            $table->index(
                ['switch_account_id', 'provisioning_enrollment_status'],
                'switch_devices_account_enrollment_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('switch_devices', function (Blueprint $table): void {
            $table->dropIndex('switch_devices_account_enrollment_index');
            $table->dropColumn([
                'provisioning_enrollment_status',
                'provisioning_enrollment_provider',
                'provisioning_enrolled_at',
                'provisioning_detached_at',
            ]);
        });
    }
};
