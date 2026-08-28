<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'switch_extensions',
        'switch_devices',
        'switch_voicemail_boxes',
        'switch_callflows',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->boolean('is_managed')->default(false)->after('projection_version');
                $table->string('managed_by_workflow', 64)->nullable()->after('is_managed');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['is_managed', 'managed_by_workflow']);
            });
        }
    }
};
