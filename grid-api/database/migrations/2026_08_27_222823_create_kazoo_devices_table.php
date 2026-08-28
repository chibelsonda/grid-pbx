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
        Schema::create('kazoo_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kazoo_account_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('kazoo_extension_id')->nullable()->constrained('kazoo_extensions')->nullOnDelete();
            $table->string('kazoo_resource_id');
            $table->string('owner_kazoo_resource_id')->nullable();
            $table->string('name')->nullable();
            $table->string('device_type', 64)->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('mac_address', 64)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_synced_at');
            $table->string('sync_status', 32)->default('healthy');
            $table->unsignedSmallInteger('projection_version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kazoo_account_id', 'kazoo_resource_id'], 'kd_account_resource_unique');
            $table->index(['kazoo_account_id', 'owner_kazoo_resource_id'], 'kd_account_owner_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kazoo_devices');
    }
};
