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
        Schema::create('switch_queues', function (Blueprint $table): void {
            $table->bigIncrements('queue_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->foreignId('music_on_hold_media_id')->nullable()->references('media_id')->on('switch_media')->nullOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->string('strategy', 20)->default('round_robin');
            $table->unsignedSmallInteger('agent_ring_timeout')->default(15);
            $table->unsignedInteger('agent_wrapup_time')->default(0);
            $table->unsignedInteger('connection_timeout')->default(3600);
            $table->unsignedInteger('max_queue_size')->default(0);
            $table->unsignedSmallInteger('ring_simultaneously')->default(1);
            $table->boolean('enter_when_empty')->default(true);
            $table->boolean('record_caller')->default(false);
            $table->string('caller_exit_key', 1)->default('#');
            $table->string('music_on_hold_reference')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['switch_account_id', 'switch_resource_id'], 'sq_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'sq_account_name_index');
        });

        Schema::create('switch_queue_agents', function (Blueprint $table): void {
            $table->bigIncrements('queue_agent_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_queue_id')->references('queue_id')->on('switch_queues')->cascadeOnDelete();
            $table->foreignId('switch_extension_id')->nullable()->references('extension_id')->on('switch_extensions')->nullOnDelete();
            $table->string('switch_user_resource_id');
            $table->timestamps();

            $table->unique(['switch_queue_id', 'switch_user_resource_id'], 'sqa_queue_user_unique');
            $table->index(['switch_user_resource_id', 'switch_queue_id'], 'sqa_user_queue_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('switch_queue_agents');
        Schema::dropIfExists('switch_queues');
    }
};
