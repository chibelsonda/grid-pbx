<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_recordings', function (Blueprint $table): void {
            $table->ulid('recording_id')->primary();
            $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->ulid('switch_extension_id')->nullable();
            $table->foreign('switch_extension_id', 'sr_extension_fk')->references('extension_id')->on('switch_extensions')->nullOnDelete();
            $table->ulid('switch_call_detail_record_id')->nullable();
            $table->foreign('switch_call_detail_record_id', 'sr_cdr_fk')->references('call_detail_record_id')->on('switch_call_detail_records')->nullOnDelete();
            $table->string('switch_resource_id');
            $table->string('owner_switch_resource_id')->nullable();
            $table->string('call_id')->nullable();
            $table->string('cdr_id')->nullable();
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
            $table->unsignedBigInteger('duration_milliseconds')->default(0);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('content_type', 128)->nullable();
            $table->unsignedBigInteger('content_length')->nullable();
            $table->string('media_source', 64)->nullable();
            $table->string('media_type', 64)->nullable();
            $table->string('source_type', 64)->nullable();
            $table->string('origin', 128)->nullable();
            $table->boolean('has_audio')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'sr_account_resource_unique');
            $table->index(['switch_account_id', 'started_at'], 'sr_account_started_index');
            $table->index(['switch_account_id', 'direction', 'started_at'], 'sr_account_direction_index');
            $table->index(['switch_account_id', 'switch_extension_id', 'started_at'], 'sr_account_extension_index');
            $table->index(['switch_account_id', 'call_id'], 'sr_account_call_index');
            $table->index(['switch_account_id', 'interaction_id'], 'sr_account_interaction_index');
        });
    }

    public function down(): void { Schema::dropIfExists('switch_recordings'); }
};
