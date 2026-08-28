<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_lifecycle_operations', function (Blueprint $table): void {
            $table->ulid('extension_lifecycle_operation_id')->primary();
            $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->constrained('switch_accounts', 'account_id')->cascadeOnDelete();
            $table->foreignUlid('switch_extension_id')->nullable()->constrained('switch_extensions', 'extension_id')->nullOnDelete();
            $table->foreignUlid('requested_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('operation', 32);
            $table->string('status', 32);
            $table->json('completed_steps');
            $table->string('failed_step')->nullable();
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['switch_extension_id', 'operation', 'status'], 'extension_lifecycle_resume_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_lifecycle_operations');
    }
};
