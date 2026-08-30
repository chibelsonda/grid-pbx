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
        Schema::create('switch_media', function (Blueprint $table): void {
            $table->bigIncrements('media_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->string('description', 128)->nullable();
            $table->string('language', 35)->nullable();
            $table->string('media_source', 32)->nullable();
            $table->string('content_type', 100)->nullable();
            $table->unsignedBigInteger('content_length')->nullable();
            $table->string('prompt_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_resource_id')->nullable();
            $table->boolean('streamable')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['switch_account_id', 'switch_resource_id'], 'sm_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'sm_account_name_index');
            $table->index(['switch_account_id', 'media_source'], 'sm_account_source_index');
        });

        Schema::table('switch_accounts', function (Blueprint $table): void {
            $table->foreignId('music_on_hold_media_id')
                ->nullable()
                ->after('realm')
                ->references('media_id')
                ->on('switch_media')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('switch_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('music_on_hold_media_id');
        });

        Schema::dropIfExists('switch_media');
    }
};
