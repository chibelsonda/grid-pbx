<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameTable('kazoo_accounts', 'switch_accounts');
        $this->renameTable('kazoo_extensions', 'switch_extensions');
        $this->renameTable('kazoo_devices', 'switch_devices');
        $this->renameTable('kazoo_voicemail_boxes', 'switch_voicemail_boxes');
        $this->renameTable('kazoo_callflows', 'switch_callflows');
        $this->renameTable('kazoo_sync_runs', 'switch_sync_runs');
        $this->renameTable('kazoo_sync_checkpoints', 'switch_sync_checkpoints');

        $this->renameColumn('switch_accounts', 'kazoo_account_id', 'switch_account_id');
        $this->renameColumn('audit_logs', 'kazoo_account_id', 'switch_account_id');
        $this->renameProjectionColumns('switch_extensions');
        $this->renameProjectionColumns('switch_devices', hasExtension: true, hasOwner: true);
        $this->renameProjectionColumns('switch_voicemail_boxes', hasExtension: true, hasOwner: true);
        $this->renameProjectionColumns('switch_callflows', hasExtension: true, hasOwner: true);
        $this->renameColumn('switch_sync_runs', 'kazoo_account_id', 'switch_account_id');
        $this->renameColumn('switch_sync_checkpoints', 'kazoo_account_id', 'switch_account_id');
    }

    public function down(): void
    {
        $this->renameColumn('switch_sync_checkpoints', 'switch_account_id', 'kazoo_account_id');
        $this->renameColumn('switch_sync_runs', 'switch_account_id', 'kazoo_account_id');
        $this->restoreProjectionColumns('switch_callflows', hasExtension: true, hasOwner: true);
        $this->restoreProjectionColumns('switch_voicemail_boxes', hasExtension: true, hasOwner: true);
        $this->restoreProjectionColumns('switch_devices', hasExtension: true, hasOwner: true);
        $this->restoreProjectionColumns('switch_extensions');
        $this->renameColumn('audit_logs', 'switch_account_id', 'kazoo_account_id');
        $this->renameColumn('switch_accounts', 'switch_account_id', 'kazoo_account_id');

        $this->renameTable('switch_sync_checkpoints', 'kazoo_sync_checkpoints');
        $this->renameTable('switch_sync_runs', 'kazoo_sync_runs');
        $this->renameTable('switch_callflows', 'kazoo_callflows');
        $this->renameTable('switch_voicemail_boxes', 'kazoo_voicemail_boxes');
        $this->renameTable('switch_devices', 'kazoo_devices');
        $this->renameTable('switch_extensions', 'kazoo_extensions');
        $this->renameTable('switch_accounts', 'kazoo_accounts');
    }

    private function renameProjectionColumns(
        string $table,
        bool $hasExtension = false,
        bool $hasOwner = false,
    ): void {
        $this->renameColumn($table, 'kazoo_account_id', 'switch_account_id');
        $this->renameColumn($table, 'kazoo_resource_id', 'switch_resource_id');

        if ($hasExtension) {
            $this->renameColumn($table, 'kazoo_extension_id', 'switch_extension_id');
        }

        if ($hasOwner) {
            $this->renameColumn($table, 'owner_kazoo_resource_id', 'owner_switch_resource_id');
        }
    }

    private function restoreProjectionColumns(
        string $table,
        bool $hasExtension = false,
        bool $hasOwner = false,
    ): void {
        if ($hasOwner) {
            $this->renameColumn($table, 'owner_switch_resource_id', 'owner_kazoo_resource_id');
        }

        if ($hasExtension) {
            $this->renameColumn($table, 'switch_extension_id', 'kazoo_extension_id');
        }

        $this->renameColumn($table, 'switch_resource_id', 'kazoo_resource_id');
        $this->renameColumn($table, 'switch_account_id', 'kazoo_account_id');
    }

    private function renameTable(string $from, string $to): void
    {
        if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            Schema::table($table, fn ($blueprint) => $blueprint->renameColumn($from, $to));
        }
    }
};
