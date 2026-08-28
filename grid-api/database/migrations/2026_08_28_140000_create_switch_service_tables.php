<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_service_summaries', function (Blueprint $table): void {
            $table->ulid('service_summary_id')->primary();
            $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->unique()->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->boolean('status_acceptable')->default(false);
            $table->string('status_reason')->nullable();
            $table->boolean('is_reseller')->default(false);
            $table->unsignedTinyInteger('billing_cycle_period')->default(0);
            $table->string('billing_cycle_unit', 24)->nullable();
            $table->timestamp('billing_cycle_next_at')->nullable();
            $table->unsignedInteger('assigned_plan_count')->default(0);
            $table->unsignedInteger('invoice_count')->default(0);
            $table->decimal('due_today', 18, 4)->default(0);
            $table->decimal('recurring_amount', 18, 4)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
        });
        Schema::create('switch_service_limits', function (Blueprint $table): void {
            $table->ulid('service_limit_id')->primary();
            $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->unique()->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->boolean('allow_prepay')->default(true);
            $table->boolean('allow_postpay')->default(false);
            $table->unsignedInteger('inbound_trunks')->default(0);
            $table->unsignedInteger('outbound_trunks')->default(0);
            $table->unsignedInteger('twoway_trunks')->default(0);
            $table->unsignedInteger('burst_trunks')->default(0);
            $table->unsignedInteger('calls')->nullable();
            $table->unsignedInteger('resource_consuming_calls')->nullable();
            $table->boolean('soft_limit_inbound')->default(false);
            $table->boolean('soft_limit_outbound')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
        });
        Schema::create('switch_service_plans', function (Blueprint $table): void {
            $table->ulid('service_plan_id')->primary();
            $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 128)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'ssp_account_resource_unique');
        });
        Schema::create('switch_service_quantities', function (Blueprint $table): void {
            $table->ulid('service_quantity_id')->primary();
            $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('scope', 24);
            $table->string('category', 128);
            $table->string('item', 128);
            $table->decimal('quantity', 20, 4)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'scope', 'category', 'item'], 'ssq_account_scope_category_item_unique');
            $table->index(['switch_account_id', 'category'], 'ssq_account_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_service_quantities');
        Schema::dropIfExists('switch_service_plans');
        Schema::dropIfExists('switch_service_limits');
        Schema::dropIfExists('switch_service_summaries');
    }
};
