<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_menus', function (Blueprint $table): void {
            $table->bigIncrements('menu_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->unsignedInteger('timeout')->default(10000);
            $table->unsignedInteger('interdigit_timeout')->default(2000);
            $table->unsignedTinyInteger('max_extension_length')->default(4);
            $table->unsignedTinyInteger('retries')->default(3);
            $table->boolean('hunt')->default(true);
            $table->boolean('allow_record_from_offnet')->default(false);
            $table->boolean('suppress_media')->default(false);
            $table->boolean('record_pin_configured')->default(false);
            $table->string('hunt_allow', 256)->nullable();
            $table->string('hunt_deny', 256)->nullable();
            foreach (['greeting', 'invalid', 'transfer', 'exit'] as $type) {
                $table->foreignId("{$type}_media_id")->nullable()->references('media_id')->on('switch_media')->nullOnDelete();
                $table->string("{$type}_media_reference")->nullable();
            }
            $table->boolean('invalid_media_enabled')->default(true);
            $table->boolean('transfer_media_enabled')->default(true);
            $table->boolean('exit_media_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'smn_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'smn_account_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_menus');
    }
};
