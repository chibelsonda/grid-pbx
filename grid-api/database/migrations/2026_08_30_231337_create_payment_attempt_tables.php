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
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->bigIncrements('payment_attempt_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->restrictOnDelete();
            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
            $table->string('provider', 32);
            $table->string('operation', 32);
            $table->char('idempotency_hash', 64);
            $table->char('request_fingerprint', 64);
            $table->decimal('amount', 24, 8)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('status', 24);
            $table->text('provider_reference')->nullable();
            $table->char('provider_reference_hash', 64)->nullable();
            $table->string('safe_error_code', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['switch_account_id', 'provider', 'idempotency_hash'],
                'payment_attempt_account_provider_idempotency_unique',
            );
            $table->index(
                ['switch_account_id', 'status', 'created_at'],
                'payment_attempt_account_status_created_index',
            );
            $table->index('provider_reference_hash');
        });

        Schema::create('payment_attempt_events', function (Blueprint $table): void {
            $table->bigIncrements('payment_attempt_event_id');
            $table->uuid('id')->unique();
            $table->foreignId('payment_attempt_id')
                ->references('payment_attempt_id')
                ->on('payment_attempts')
                ->restrictOnDelete();
            $table->string('event_type', 64);
            $table->string('status', 24)->nullable();
            $table->char('provider_reference_hash', 64)->nullable();
            $table->json('safe_context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['payment_attempt_id', 'created_at'],
                'payment_attempt_event_attempt_created_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempt_events');
        Schema::dropIfExists('payment_attempts');
    }
};
