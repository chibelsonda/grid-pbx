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
        Schema::create('switch_groups', function (Blueprint $table): void {
            $table->bigIncrements('group_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->foreignId('music_on_hold_media_id')->nullable()->references('media_id')->on('switch_media')->nullOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['switch_account_id', 'switch_resource_id'], 'sg_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'sg_account_name_index');
        });

        Schema::create('switch_group_members', function (Blueprint $table): void {
            $table->bigIncrements('group_member_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_group_id')->references('group_id')->on('switch_groups')->cascadeOnDelete();
            $table->foreignId('switch_extension_id')->nullable()->references('extension_id')->on('switch_extensions')->nullOnDelete();
            $table->foreignId('switch_device_id')->nullable()->references('device_id')->on('switch_devices')->nullOnDelete();
            $table->foreignId('nested_switch_group_id')->nullable()->references('group_id')->on('switch_groups')->nullOnDelete();
            $table->string('member_type', 16);
            $table->string('switch_member_resource_id');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamps();

            $table->unique(['switch_group_id', 'member_type', 'switch_member_resource_id'], 'sgm_group_member_unique');
            $table->index(['member_type', 'switch_member_resource_id'], 'sgm_resource_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('switch_group_members');
        Schema::dropIfExists('switch_groups');
    }
};
