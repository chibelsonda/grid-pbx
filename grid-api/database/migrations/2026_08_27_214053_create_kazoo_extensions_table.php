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
        Schema::create('kazoo_extensions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kazoo_account_id')->constrained()->cascadeOnDelete();
            $table->string('kazoo_resource_id');
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name');
            $table->string('email')->nullable();
            $table->string('extension', 64)->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('source_revision')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at');
            $table->string('sync_status', 32)->default('healthy');
            $table->unsignedSmallInteger('projection_version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kazoo_account_id', 'kazoo_resource_id']);
            $table->index(['kazoo_account_id', 'extension']);
            $table->index(['kazoo_account_id', 'display_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kazoo_extensions');
    }
};
