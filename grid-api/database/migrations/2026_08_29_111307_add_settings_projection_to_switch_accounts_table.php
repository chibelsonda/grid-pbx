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
        Schema::table('switch_accounts', function (Blueprint $table): void {
            $table->string('org_name')->nullable()->after('name');
            $table->string('language', 32)->nullable()->after('timezone');
            $table->boolean('call_waiting_enabled')->nullable()->after('is_enabled');
            $table->boolean('do_not_disturb_enabled')->nullable()->after('call_waiting_enabled');
            $table->string('outbound_privacy', 16)->nullable()->after('do_not_disturb_enabled');
            $table->string('ringtone_internal')->nullable()->after('outbound_privacy');
            $table->string('ringtone_external')->nullable()->after('ringtone_internal');
            $table->timestamp('last_synced_at')->nullable()->after('ringtone_external');
            $table->string('sync_status', 32)->default('stale')->after('last_synced_at');
            $table->unsignedInteger('projection_version')->default(0)->after('sync_status');
            $table->json('switch_json')->nullable()->after('projection_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('switch_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'org_name',
                'language',
                'call_waiting_enabled',
                'do_not_disturb_enabled',
                'outbound_privacy',
                'ringtone_internal',
                'ringtone_external',
                'last_synced_at',
                'sync_status',
                'projection_version',
                'switch_json',
            ]);
        });
    }
};
