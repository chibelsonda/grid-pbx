<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->foreignId('source_payment_attempt_id')
                ->nullable()
                ->after('requested_by_user_id')
                ->references('payment_attempt_id')
                ->on('payment_attempts')
                ->restrictOnDelete();

            $table->index(
                ['source_payment_attempt_id', 'operation', 'status'],
                'payment_attempt_source_operation_status_index',
            );
        });

        Schema::create('payment_customer_profiles', function (Blueprint $table): void {
            $table->bigIncrements('payment_customer_profile_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->restrictOnDelete();
            $table->foreignId('source_payment_attempt_id')
                ->references('payment_attempt_id')
                ->on('payment_attempts')
                ->restrictOnDelete();
            $table->foreignId('created_by_payment_attempt_id')
                ->references('payment_attempt_id')
                ->on('payment_attempts')
                ->restrictOnDelete();
            $table->string('provider', 32);
            $table->text('provider_customer_profile_id');
            $table->char('provider_customer_profile_hash', 64);
            $table->text('provider_payment_profile_id');
            $table->char('provider_payment_profile_hash', 64);
            $table->string('status', 24)->default('active');
            $table->string('masked_account', 24)->nullable();
            $table->string('account_type', 32)->nullable();
            $table->timestamps();

            $table->unique(
                ['source_payment_attempt_id', 'provider'],
                'payment_profile_source_provider_unique',
            );
            $table->unique(
                ['switch_account_id', 'provider', 'provider_payment_profile_hash'],
                'payment_profile_account_provider_payment_hash_unique',
            );
            $table->unique('created_by_payment_attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_customer_profiles');

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropIndex('payment_attempt_source_operation_status_index');
            $table->dropForeign(['source_payment_attempt_id']);
            $table->dropColumn('source_payment_attempt_id');
        });
    }
};
