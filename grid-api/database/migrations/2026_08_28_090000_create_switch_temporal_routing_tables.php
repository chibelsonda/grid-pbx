<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_temporal_rules', function (Blueprint $table): void {
            $table->ulid('temporal_rule_id')->primary();
            $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->string('cycle', 16);
            $table->unsignedInteger('interval')->default(1);
            $table->date('start_date')->nullable();
            $table->unsignedBigInteger('switch_start_date')->nullable();
            $table->unsignedInteger('time_window_start')->nullable();
            $table->unsignedInteger('time_window_stop')->nullable();
            $table->boolean('enabled')->nullable();
            $table->json('days')->nullable();
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->string('ordinal', 16)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'str_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'str_account_name_index');
        });
        Schema::create('switch_temporal_rule_sets', function (Blueprint $table): void {
            $table->ulid('temporal_rule_set_id')->primary();
            $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'strs_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'strs_account_name_index');
        });
        Schema::create('switch_temporal_rule_set_rules', function (Blueprint $table): void {
            $table->ulid('temporal_rule_set_rule_id')->primary();
            $table->uuid('id')->unique();
            $table->ulid('switch_temporal_rule_set_id');
            $table->foreign('switch_temporal_rule_set_id', 'strsr_set_fk')->references('temporal_rule_set_id')->on('switch_temporal_rule_sets')->cascadeOnDelete();
            $table->ulid('switch_temporal_rule_id')->nullable();
            $table->foreign('switch_temporal_rule_id', 'strsr_rule_fk')->references('temporal_rule_id')->on('switch_temporal_rules')->nullOnDelete();
            $table->string('switch_rule_resource_id');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['switch_temporal_rule_set_id', 'switch_rule_resource_id'], 'strsr_set_rule_unique');
            $table->unique(['switch_temporal_rule_set_id', 'position'], 'strsr_set_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_temporal_rule_set_rules');
        Schema::dropIfExists('switch_temporal_rule_sets');
        Schema::dropIfExists('switch_temporal_rules');
    }
};
