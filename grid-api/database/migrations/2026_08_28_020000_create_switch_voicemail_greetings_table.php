<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_voicemail_greetings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('switch_account_id')->constrained('switch_accounts')->cascadeOnDelete();
            $table->foreignUlid('switch_voicemail_box_id')->constrained('switch_voicemail_boxes')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('type', 32)->default('unavailable');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('content_length')->nullable();
            $table->string('media_source')->nullable();
            $table->boolean('streamable')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['switch_voicemail_box_id', 'type'], 'svg_box_type_unique');
            $table->index(['switch_account_id', 'switch_resource_id'], 'svg_account_resource_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_voicemail_greetings');
    }
};
