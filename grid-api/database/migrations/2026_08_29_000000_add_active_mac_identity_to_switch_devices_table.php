<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switch_devices', function (Blueprint $table): void {
            $table->string('active_mac_identity', 12)
                ->nullable()
                ->virtualAs(
                    'CASE WHEN deleted_at IS NULL AND mac_address IS NOT NULL '
                    ."AND LENGTH(REPLACE(REPLACE(mac_address, ':', ''), '-', '')) = 12 "
                    ."THEN UPPER(REPLACE(REPLACE(mac_address, ':', ''), '-', '')) ELSE NULL END",
                );
            $table->unique(
                ['switch_account_id', 'active_mac_identity'],
                'sd_account_active_mac_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('switch_devices', function (Blueprint $table): void {
            $table->dropUnique('sd_account_active_mac_unique');
            $table->dropColumn('active_mac_identity');
        });
    }
};
