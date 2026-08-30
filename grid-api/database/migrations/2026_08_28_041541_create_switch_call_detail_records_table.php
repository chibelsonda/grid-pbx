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
        Schema::create('switch_call_detail_records', function (Blueprint $table): void {
            $table->bigIncrements('call_detail_record_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id');
            $table->foreignId('switch_extension_id')->nullable();
            $table->string('switch_resource_id');
            $table->string('call_id');
            $table->string('interaction_id')->nullable();
            $table->string('direction', 16)->nullable();
            $table->string('caller_id_name')->nullable();
            $table->string('caller_id_number', 64)->nullable();
            $table->string('callee_id_name')->nullable();
            $table->string('callee_id_number', 64)->nullable();
            $table->string('from_uri')->nullable();
            $table->string('to_uri')->nullable();
            $table->string('request_uri')->nullable();
            $table->timestamp('started_at');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('billing_seconds')->default(0);
            $table->string('hangup_cause', 64)->nullable();
            $table->string('disposition', 64)->nullable();
            $table->boolean('recording_available')->default(false);
            $table->timestamp('last_synced_at');
            $table->json('switch_json');
            $table->timestamps();

            $table->foreign('switch_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->cascadeOnDelete();
            $table->foreign('switch_extension_id')
                ->references('extension_id')
                ->on('switch_extensions')
                ->nullOnDelete();
            $table->unique(
                ['switch_account_id', 'switch_resource_id'],
                'switch_cdrs_account_resource_unique',
            );
            $table->index(['switch_account_id', 'started_at'], 'switch_cdrs_account_started_index');
            $table->index(['switch_account_id', 'direction', 'started_at'], 'switch_cdrs_account_direction_index');
            $table->index(['switch_account_id', 'switch_extension_id', 'started_at'], 'switch_cdrs_account_extension_index');
            $table->index(['switch_account_id', 'interaction_id', 'started_at'], 'switch_cdrs_account_interaction_index');
            $table->index(['switch_account_id', 'hangup_cause', 'started_at'], 'switch_cdrs_account_cause_index');
            $table->index(['switch_account_id', 'duration_seconds'], 'switch_cdrs_account_duration_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('switch_call_detail_records');
    }
};
