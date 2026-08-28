<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switch_callflows', function (Blueprint $table): void {
            $table->json('patterns')->nullable()->after('numbers');
            $table->json('flags')->nullable()->after('patterns');
            $table->string('root_module', 64)->nullable()->after('modules');
            $table->unsignedSmallInteger('node_count')->default(0)->after('root_module');
            $table->unsignedSmallInteger('max_depth')->default(0)->after('node_count');
            $table->boolean('is_feature_code')->default(false)->after('max_depth');
            $table->string('feature_code_name', 128)->nullable()->after('is_feature_code');
            $table->string('feature_code_number', 30)->nullable()->after('feature_code_name');
            $table->json('flow_structure')->nullable()->after('feature_code_number');

            $table->index(['switch_account_id', 'root_module'], 'switch_callflows_account_root_module_index');
            $table->index(['switch_account_id', 'is_feature_code'], 'switch_callflows_account_feature_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('switch_callflows', function (Blueprint $table): void {
            $table->dropIndex('switch_callflows_account_root_module_index');
            $table->dropIndex('switch_callflows_account_feature_code_index');
            $table->dropColumn([
                'patterns',
                'flags',
                'root_module',
                'node_count',
                'max_depth',
                'is_feature_code',
                'feature_code_name',
                'feature_code_number',
                'flow_structure',
            ]);
        });
    }
};
