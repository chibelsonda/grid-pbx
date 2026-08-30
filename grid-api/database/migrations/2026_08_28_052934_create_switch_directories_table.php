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
        Schema::create('switch_directories', function (Blueprint $table): void {
            $table->bigIncrements('directory_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->boolean('confirm_match')->default(true);
            $table->unsignedSmallInteger('min_dtmf')->default(3);
            $table->unsignedSmallInteger('max_dtmf')->default(0);
            $table->string('sort_by', 16)->default('last_name');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['switch_account_id', 'switch_resource_id'], 'sd_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'sd_account_name_index');
        });

        Schema::create('switch_directory_members', function (Blueprint $table): void {
            $table->bigIncrements('directory_member_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_directory_id')->references('directory_id')->on('switch_directories')->cascadeOnDelete();
            $table->foreignId('switch_extension_id')->nullable()->references('extension_id')->on('switch_extensions')->nullOnDelete();
            $table->foreignId('switch_callflow_id')->nullable()->references('callflow_id')->on('switch_callflows')->nullOnDelete();
            $table->string('switch_user_resource_id');
            $table->string('switch_callflow_resource_id');
            $table->timestamps();

            $table->unique(['switch_directory_id', 'switch_user_resource_id'], 'sdm_directory_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('switch_directory_members');
        Schema::dropIfExists('switch_directories');
    }
};
