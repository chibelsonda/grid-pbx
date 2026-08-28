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
        Schema::create('kazoo_voicemail_boxes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kazoo_account_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('kazoo_extension_id')->nullable()->constrained('kazoo_extensions')->nullOnDelete();
            $table->string('kazoo_resource_id');
            $table->string('owner_kazoo_resource_id')->nullable();
            $table->string('name')->nullable();
            $table->string('mailbox', 64)->nullable();
            $table->boolean('is_setup')->nullable();
            $table->timestamp('last_synced_at');
            $table->string('sync_status', 32)->default('healthy');
            $table->unsignedSmallInteger('projection_version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kazoo_account_id', 'kazoo_resource_id'], 'kvb_account_resource_unique');
            $table->index(['kazoo_account_id', 'owner_kazoo_resource_id'], 'kvb_account_owner_index');
            $table->index(['kazoo_account_id', 'mailbox'], 'kvb_account_mailbox_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kazoo_voicemail_boxes');
    }
};
