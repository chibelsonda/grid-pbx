<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_phone_numbers', function (Blueprint $table): void {
            $table->bigIncrements('phone_number_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id');
            $table->foreignId('assigned_callflow_id')->nullable();
            $table->string('number', 64);
            $table->string('state', 32)->nullable();
            $table->string('used_by', 64)->nullable();
            $table->string('assigned_to_switch_account_id')->nullable();
            $table->string('carrier_name', 64)->nullable();
            $table->json('features');
            $table->string('cnam_display_name', 32)->nullable();
            $table->boolean('cnam_inbound_lookup')->default(false);
            $table->string('e911_status', 32)->nullable();
            $table->unsignedBigInteger('source_created_timestamp')->nullable();
            $table->unsignedBigInteger('source_updated_timestamp')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 32)->default('healthy');
            $table->unsignedSmallInteger('projection_version')->default(1);
            $table->json('switch_json');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('switch_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->cascadeOnDelete();
            $table->foreign('assigned_callflow_id')
                ->references('callflow_id')
                ->on('switch_callflows')
                ->nullOnDelete();
            $table->unique(['switch_account_id', 'number'], 'switch_phone_numbers_account_number_unique');
            $table->index(['switch_account_id', 'state'], 'switch_phone_numbers_account_state_index');
            $table->index(['switch_account_id', 'used_by'], 'switch_phone_numbers_account_usage_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_phone_numbers');
    }
};
