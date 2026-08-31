<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->string('provider_status', 64)->nullable()->after('safe_error_code');
            $table->timestamp('reconciled_at')->nullable()->after('provider_status');
        });

        Schema::create('payment_webhook_deliveries', function (Blueprint $table): void {
            $table->bigIncrements('payment_webhook_delivery_id');
            $table->uuid('id')->unique();
            $table->string('provider', 32);
            $table->char('notification_hash', 64);
            $table->string('event_type', 128);
            $table->string('entity_name', 64);
            $table->text('provider_reference')->nullable();
            $table->char('provider_reference_hash', 64)->nullable();
            $table->string('merchant_reference', 64)->nullable();
            $table->foreignId('payment_attempt_id')
                ->nullable()
                ->references('payment_attempt_id')
                ->on('payment_attempts')
                ->nullOnDelete();
            $table->string('status', 24);
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->string('safe_error_code', 64)->nullable();
            $table->timestamp('event_occurred_at')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'notification_hash'],
                'payment_webhook_provider_notification_unique',
            );
            $table->index(
                ['status', 'received_at'],
                'payment_webhook_status_received_index',
            );
            $table->index('provider_reference_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_deliveries');

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropColumn(['provider_status', 'reconciled_at']);
        });
    }
};
