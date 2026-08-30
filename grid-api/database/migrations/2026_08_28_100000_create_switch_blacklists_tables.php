<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_blacklists', function (Blueprint $table): void {
            $table->bigIncrements('blacklist_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->boolean('should_block_anonymous')->default(false);
            $table->boolean('is_active')->default(false);
            $table->json('flags')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'sbl_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'sbl_account_name_index');
        });
        Schema::create('switch_blacklist_entries', function (Blueprint $table): void {
            $table->bigIncrements('blacklist_entry_id');
            $table->uuid('id')->unique();
            $table->unsignedBigInteger('switch_blacklist_id');
            $table->foreign('switch_blacklist_id', 'sble_blacklist_fk')->references('blacklist_id')->on('switch_blacklists')->cascadeOnDelete();
            $table->string('number', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['switch_blacklist_id', 'number'], 'sble_blacklist_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_blacklist_entries');
        Schema::dropIfExists('switch_blacklists');
    }
};
