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
        Schema::create('kazoo_sync_checkpoints', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('kazoo_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_sync_run_id')->nullable()->constrained('kazoo_sync_runs')->nullOnDelete();
            $table->string('resource_type', 64);
            $table->text('cursor')->nullable();
            $table->string('status', 32)->default('stale');
            $table->timestamp('last_successful_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['kazoo_account_id', 'resource_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kazoo_sync_checkpoints');
    }
};
