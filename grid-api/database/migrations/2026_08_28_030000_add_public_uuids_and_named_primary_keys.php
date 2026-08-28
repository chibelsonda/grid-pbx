<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PRIMARY_KEYS = [
        'users' => 'user_id',
        'organizations' => 'organization_id',
        'switch_accounts' => 'account_id',
        'audit_logs' => 'audit_log_id',
        'switch_extensions' => 'extension_id',
        'switch_devices' => 'device_id',
        'switch_voicemail_boxes' => 'voicemail_box_id',
        'switch_callflows' => 'callflow_id',
        'switch_sync_runs' => 'sync_run_id',
        'switch_sync_checkpoints' => 'sync_checkpoint_id',
        'switch_voicemail_messages' => 'voicemail_message_id',
        'switch_voicemail_greetings' => 'voicemail_greeting_id',
    ];

    public function up(): void
    {
        foreach (self::PRIMARY_KEYS as $tableName => $primaryKey) {
            Schema::table($tableName, function (Blueprint $table) use ($primaryKey): void {
                $table->renameColumn('id', $primaryKey);
            });

            $this->addPublicUuid($tableName, $primaryKey);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::PRIMARY_KEYS, true) as $tableName => $primaryKey) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['id']);
                $table->dropColumn('id');
            });

            Schema::table($tableName, function (Blueprint $table) use ($primaryKey): void {
                $table->renameColumn($primaryKey, 'id');
            });
        }
    }

    private function addPublicUuid(string $tableName, string $primaryKey): void
    {
        $hasExistingRows = DB::table($tableName)->exists();

        Schema::table($tableName, function (Blueprint $table) use ($hasExistingRows, $primaryKey): void {
            $column = $table->uuid('id')->after($primaryKey);

            if ($hasExistingRows) {
                $column->nullable();
            }

            $column->unique();
        });

        if (! $hasExistingRows) {
            return;
        }

        DB::table($tableName)
            ->select($primaryKey)
            ->orderBy($primaryKey)
            ->chunk(100, function ($rows) use ($tableName, $primaryKey): void {
                foreach ($rows as $row) {
                    DB::table($tableName)
                        ->where($primaryKey, $row->{$primaryKey})
                        ->update(['id' => (string) Str::uuid()]);
                }
            });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->uuid('id')->nullable(false)->change();
        });
    }
};
