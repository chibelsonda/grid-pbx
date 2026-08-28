<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_voicemail_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('switch_account_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('switch_voicemail_box_id')->constrained('switch_voicemail_boxes')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('folder', 16)->nullable();
            $table->string('caller_id_name', 128)->nullable();
            $table->string('caller_id_number', 64)->nullable();
            $table->string('from_address', 255)->nullable();
            $table->string('to_address', 255)->nullable();
            $table->unsignedInteger('length')->nullable();
            $table->unsignedBigInteger('source_timestamp')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('transcription_result', 32)->nullable();
            $table->text('transcription_text')->nullable();
            $table->timestamp('last_synced_at');
            $table->string('sync_status', 32)->default('healthy');
            $table->unsignedSmallInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['switch_account_id', 'switch_resource_id'], 'svm_account_resource_unique');
            $table->index(['switch_voicemail_box_id', 'folder', 'occurred_at'], 'svm_box_folder_occurred_index');
            $table->index(['switch_account_id', 'occurred_at'], 'svm_account_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_voicemail_messages');
    }
};
