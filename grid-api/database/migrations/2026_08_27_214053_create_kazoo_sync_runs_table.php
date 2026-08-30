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
        Schema::create('kazoo_sync_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('kazoo_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource_type', 64);
            $table->string('status', 32);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('upserted_count')->default(0);
            $table->unsignedInteger('deleted_count')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['kazoo_account_id', 'resource_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kazoo_sync_runs');
    }
};
