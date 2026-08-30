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
            $table->unsignedBigInteger('parent_account_id')->nullable()->after('organization_id');
            $table->string('parent_switch_account_id')->nullable()->after('switch_account_id');
            $table->boolean('is_reseller')->default(false)->after('is_enabled');
            $table->boolean('is_superduper_admin')->default(false)->after('is_reseller');
            $table->string('billing_mode', 48)->nullable()->after('is_superduper_admin');
            $table->unsignedInteger('descendants_count')->default(0)->after('billing_mode');
            $table->timestamp('hierarchy_synced_at')->nullable()->after('descendants_count');

            $table->foreign('parent_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->nullOnDelete();
            $table->index(['organization_id', 'parent_account_id'], 'switch_accounts_org_parent_index');
        });

        Schema::table('switch_service_summaries', function (Blueprint $table): void {
            $table->unsignedBigInteger('billing_reseller_account_id')->nullable()->after('switch_account_id');
            $table->string('billing_reseller_switch_account_id')->nullable()->after('billing_reseller_account_id');

            $table->foreign('billing_reseller_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('switch_service_summaries', function (Blueprint $table): void {
            $table->dropForeign(['billing_reseller_account_id']);
            $table->dropColumn([
                'billing_reseller_account_id',
                'billing_reseller_switch_account_id',
            ]);
        });

        Schema::table('switch_accounts', function (Blueprint $table): void {
            $table->dropForeign(['parent_account_id']);
            $table->dropIndex('switch_accounts_org_parent_index');
            $table->dropColumn([
                'parent_account_id',
                'parent_switch_account_id',
                'is_reseller',
                'is_superduper_admin',
                'billing_mode',
                'descendants_count',
                'hierarchy_synced_at',
            ]);
        });
    }
};
