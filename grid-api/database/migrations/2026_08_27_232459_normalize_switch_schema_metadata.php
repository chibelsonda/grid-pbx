<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropForeignKeys(toSwitch: true);
        $this->renameIndexes(toSwitch: true);
        $this->createForeignKeys(toSwitch: true);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropForeignKeys(toSwitch: false);
        $this->renameIndexes(toSwitch: false);
        $this->createForeignKeys(toSwitch: false);
    }

    private function dropForeignKeys(bool $toSwitch): void
    {
        foreach ($this->foreignKeys() as $foreignKey) {
            $constraint = $toSwitch ? $foreignKey['legacy'] : $foreignKey['switch'];

            if (! $this->constraintExists($foreignKey['table'], $constraint)) {
                continue;
            }

            Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey, $toSwitch): void {
                $table->dropForeign($toSwitch ? $foreignKey['legacy'] : $foreignKey['switch']);
            });
        }
    }

    private function renameIndexes(bool $toSwitch): void
    {
        foreach ($this->indexes() as $index) {
            $from = $toSwitch ? $index['legacy'] : $index['switch'];
            $to = $toSwitch ? $index['switch'] : $index['legacy'];

            if (! $this->indexExists($index['table'], $from) || $this->indexExists($index['table'], $to)) {
                continue;
            }

            Schema::table($index['table'], function (Blueprint $table) use ($index, $toSwitch): void {
                $table->renameIndex(
                    $toSwitch ? $index['legacy'] : $index['switch'],
                    $toSwitch ? $index['switch'] : $index['legacy'],
                );
            });
        }
    }

    private function createForeignKeys(bool $toSwitch): void
    {
        foreach ($this->foreignKeys() as $foreignKey) {
            $constraint = $toSwitch ? $foreignKey['switch'] : $foreignKey['legacy'];

            if ($this->constraintExists($foreignKey['table'], $constraint)) {
                continue;
            }

            Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey, $toSwitch): void {
                $table->foreign(
                    $foreignKey['column'],
                    $toSwitch ? $foreignKey['switch'] : $foreignKey['legacy'],
                )
                    ->references('id')
                    ->on($foreignKey['references'])
                    ->onDelete($foreignKey['on_delete']);
            });
        }
    }

    /**
     * @return list<array{table: string, column: string, references: string, on_delete: string, legacy: string, switch: string}>
     */
    private function foreignKeys(): array
    {
        return [
            ['table' => 'audit_logs', 'column' => 'switch_account_id', 'references' => 'switch_accounts', 'on_delete' => 'set null', 'legacy' => 'audit_logs_kazoo_account_id_foreign', 'switch' => 'audit_logs_switch_account_id_foreign'],
            ['table' => 'switch_accounts', 'column' => 'organization_id', 'references' => 'organizations', 'on_delete' => 'cascade', 'legacy' => 'kazoo_accounts_organization_id_foreign', 'switch' => 'switch_accounts_organization_id_foreign'],
            ['table' => 'switch_extensions', 'column' => 'switch_account_id', 'references' => 'switch_accounts', 'on_delete' => 'cascade', 'legacy' => 'kazoo_extensions_kazoo_account_id_foreign', 'switch' => 'switch_extensions_switch_account_id_foreign'],
            ['table' => 'switch_devices', 'column' => 'switch_account_id', 'references' => 'switch_accounts', 'on_delete' => 'cascade', 'legacy' => 'kazoo_devices_kazoo_account_id_foreign', 'switch' => 'switch_devices_switch_account_id_foreign'],
            ['table' => 'switch_devices', 'column' => 'switch_extension_id', 'references' => 'switch_extensions', 'on_delete' => 'set null', 'legacy' => 'kazoo_devices_kazoo_extension_id_foreign', 'switch' => 'switch_devices_switch_extension_id_foreign'],
            ['table' => 'switch_voicemail_boxes', 'column' => 'switch_account_id', 'references' => 'switch_accounts', 'on_delete' => 'cascade', 'legacy' => 'kazoo_voicemail_boxes_kazoo_account_id_foreign', 'switch' => 'switch_voicemail_boxes_switch_account_id_foreign'],
            ['table' => 'switch_voicemail_boxes', 'column' => 'switch_extension_id', 'references' => 'switch_extensions', 'on_delete' => 'set null', 'legacy' => 'kazoo_voicemail_boxes_kazoo_extension_id_foreign', 'switch' => 'switch_voicemail_boxes_switch_extension_id_foreign'],
            ['table' => 'switch_callflows', 'column' => 'switch_account_id', 'references' => 'switch_accounts', 'on_delete' => 'cascade', 'legacy' => 'kazoo_callflows_kazoo_account_id_foreign', 'switch' => 'switch_callflows_switch_account_id_foreign'],
            ['table' => 'switch_callflows', 'column' => 'switch_extension_id', 'references' => 'switch_extensions', 'on_delete' => 'set null', 'legacy' => 'kazoo_callflows_kazoo_extension_id_foreign', 'switch' => 'switch_callflows_switch_extension_id_foreign'],
            ['table' => 'switch_sync_runs', 'column' => 'switch_account_id', 'references' => 'switch_accounts', 'on_delete' => 'cascade', 'legacy' => 'kazoo_sync_runs_kazoo_account_id_foreign', 'switch' => 'switch_sync_runs_switch_account_id_foreign'],
            ['table' => 'switch_sync_runs', 'column' => 'requested_by_user_id', 'references' => 'users', 'on_delete' => 'set null', 'legacy' => 'kazoo_sync_runs_requested_by_user_id_foreign', 'switch' => 'switch_sync_runs_requested_by_user_id_foreign'],
            ['table' => 'switch_sync_checkpoints', 'column' => 'switch_account_id', 'references' => 'switch_accounts', 'on_delete' => 'cascade', 'legacy' => 'kazoo_sync_checkpoints_kazoo_account_id_foreign', 'switch' => 'switch_sync_checkpoints_switch_account_id_foreign'],
            ['table' => 'switch_sync_checkpoints', 'column' => 'last_sync_run_id', 'references' => 'switch_sync_runs', 'on_delete' => 'set null', 'legacy' => 'kazoo_sync_checkpoints_last_sync_run_id_foreign', 'switch' => 'switch_sync_checkpoints_last_sync_run_id_foreign'],
        ];
    }

    /** @return list<array{table: string, legacy: string, switch: string}> */
    private function indexes(): array
    {
        return [
            ['table' => 'audit_logs', 'legacy' => 'audit_logs_kazoo_account_id_foreign', 'switch' => 'audit_logs_switch_account_id_foreign'],
            ['table' => 'switch_devices', 'legacy' => 'kazoo_devices_kazoo_extension_id_foreign', 'switch' => 'switch_devices_switch_extension_id_foreign'],
            ['table' => 'switch_voicemail_boxes', 'legacy' => 'kazoo_voicemail_boxes_kazoo_extension_id_foreign', 'switch' => 'switch_voicemail_boxes_switch_extension_id_foreign'],
            ['table' => 'switch_callflows', 'legacy' => 'kazoo_callflows_kazoo_extension_id_foreign', 'switch' => 'switch_callflows_switch_extension_id_foreign'],
            ['table' => 'switch_sync_runs', 'legacy' => 'kazoo_sync_runs_requested_by_user_id_foreign', 'switch' => 'switch_sync_runs_requested_by_user_id_foreign'],
            ['table' => 'switch_sync_checkpoints', 'legacy' => 'kazoo_sync_checkpoints_last_sync_run_id_foreign', 'switch' => 'switch_sync_checkpoints_last_sync_run_id_foreign'],
            ['table' => 'switch_accounts', 'legacy' => 'kazoo_accounts_organization_id_kazoo_account_id_unique', 'switch' => 'switch_accounts_organization_id_switch_account_id_unique'],
            ['table' => 'switch_accounts', 'legacy' => 'kazoo_accounts_kazoo_account_id_index', 'switch' => 'switch_accounts_switch_account_id_index'],
            ['table' => 'switch_extensions', 'legacy' => 'kazoo_extensions_kazoo_account_id_kazoo_resource_id_unique', 'switch' => 'switch_extensions_switch_account_id_switch_resource_id_unique'],
            ['table' => 'switch_extensions', 'legacy' => 'kazoo_extensions_kazoo_account_id_extension_index', 'switch' => 'switch_extensions_switch_account_id_extension_index'],
            ['table' => 'switch_extensions', 'legacy' => 'kazoo_extensions_kazoo_account_id_display_name_index', 'switch' => 'switch_extensions_switch_account_id_display_name_index'],
            ['table' => 'switch_sync_runs', 'legacy' => 'kazoo_sync_runs_kazoo_account_id_resource_type_created_at_index', 'switch' => 'switch_sync_runs_account_resource_created_index'],
            ['table' => 'switch_sync_checkpoints', 'legacy' => 'kazoo_sync_checkpoints_kazoo_account_id_resource_type_unique', 'switch' => 'switch_sync_checkpoints_account_resource_unique'],
        ];
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
