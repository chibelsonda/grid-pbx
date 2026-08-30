<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_billing_summaries', function (Blueprint $table): void {
            $table->bigIncrements('billing_summary_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->unique()->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->decimal('ledger_total', 24, 8)->nullable();
            $table->unsignedInteger('ledger_source_count')->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->boolean('ledgers_available')->default(false);
            $table->boolean('ledger_total_available')->default(false);
            $table->boolean('transactions_available')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
        });

        Schema::create('switch_ledger_summaries', function (Blueprint $table): void {
            $table->bigIncrements('ledger_summary_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('source_service', 128);
            $table->decimal('amount', 24, 8);
            $table->decimal('usage_quantity', 24, 8)->nullable();
            $table->string('usage_type', 64)->nullable();
            $table->string('usage_unit', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'source_service'], 'sls_account_source_unique');
        });

        Schema::create('switch_billing_transactions', function (Blueprint $table): void {
            $table->bigIncrements('billing_transaction_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('switch_resource_id', 128);
            $table->decimal('amount', 24, 8);
            $table->string('type', 32)->nullable();
            $table->string('reason', 128)->nullable();
            $table->text('description')->nullable();
            $table->integer('code')->nullable();
            $table->unsignedInteger('switch_version')->nullable();
            $table->timestamp('switch_created_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'sbt_account_resource_unique');
            $table->index(['switch_account_id', 'switch_created_at'], 'sbt_account_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_billing_transactions');
        Schema::dropIfExists('switch_ledger_summaries');
        Schema::dropIfExists('switch_billing_summaries');
    }
};
