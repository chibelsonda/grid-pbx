<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->projectionTables() as $tableName) {
            if (Schema::hasColumn($tableName, 'source_payload')
                && ! Schema::hasColumn($tableName, 'switch_json')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->renameColumn('source_payload', 'switch_json');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->projectionTables() as $tableName) {
            if (Schema::hasColumn($tableName, 'switch_json')
                && ! Schema::hasColumn($tableName, 'source_payload')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->renameColumn('switch_json', 'source_payload');
                });
            }
        }
    }

    /** @return list<string> */
    private function projectionTables(): array
    {
        return [
            'switch_extensions',
            'switch_devices',
            'switch_voicemail_boxes',
            'switch_callflows',
        ];
    }
};
