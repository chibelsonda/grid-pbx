<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_conferences', function (Blueprint $table): void {
            $table->bigIncrements('conference_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->foreignId('owner_extension_id')->nullable()->references('extension_id')->on('switch_extensions')->nullOnDelete();
            $table->string('switch_resource_id');
            $table->string('owner_switch_resource_id')->nullable();
            $table->string('name', 128);
            $table->boolean('member_pin_configured')->default(false);
            $table->boolean('moderator_pin_configured')->default(false);
            $table->boolean('member_join_muted')->default(true);
            $table->boolean('member_join_deaf')->default(false);
            $table->boolean('member_play_entry_prompt')->default(false);
            $table->boolean('moderator_join_muted')->default(false);
            $table->boolean('moderator_join_deaf')->default(false);
            $table->unsignedInteger('max_participants')->nullable();
            $table->string('language', 16)->nullable();
            $table->string('profile_name', 128)->nullable();
            $table->string('caller_controls', 128)->nullable();
            $table->string('moderator_controls', 128)->nullable();
            $table->boolean('play_name')->default(false);
            $table->boolean('play_welcome')->default(true);
            $table->boolean('require_moderator')->default(false);
            $table->boolean('wait_for_moderator')->default(false);
            $table->unsignedInteger('active_members')->default(0);
            $table->unsignedInteger('active_moderators')->default(0);
            $table->unsignedBigInteger('duration_seconds')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'scf_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'scf_account_name_index');
        });
        Schema::create('switch_conference_numbers', function (Blueprint $table): void {
            $table->bigIncrements('conference_number_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_conference_id')->references('conference_id')->on('switch_conferences')->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('number', 32);
            $table->timestamps();
            $table->unique(['switch_conference_id', 'role', 'number'], 'scn_conference_role_number_unique');
            $table->index(['number', 'role'], 'scn_number_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_conference_numbers');
        Schema::dropIfExists('switch_conferences');
    }
};
