<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_fax_boxes', function (Blueprint $table): void {
            $table->ulid('fax_box_id')->primary(); $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->foreignUlid('owner_extension_id')->nullable()->references('extension_id')->on('switch_extensions')->nullOnDelete();
            $table->string('switch_resource_id'); $table->string('owner_switch_resource_id')->nullable(); $table->string('name', 128);
            $table->string('caller_id', 64)->nullable(); $table->string('caller_name', 128)->nullable(); $table->string('fax_header', 128)->nullable(); $table->string('fax_identity', 64)->nullable(); $table->string('fax_timezone', 64)->nullable();
            $table->unsignedTinyInteger('retries')->default(1); $table->boolean('t38_enabled')->default(false);
            $table->string('smtp_email_address')->nullable(); $table->string('custom_smtp_email_address')->nullable();
            $table->json('smtp_permission_list')->nullable(); $table->json('inbound_notification_emails')->nullable(); $table->json('outbound_notification_emails')->nullable();
            $table->timestamp('last_synced_at')->nullable(); $table->string('sync_status', 20)->default('stale'); $table->unsignedInteger('projection_version')->default(1); $table->json('switch_json')->nullable(); $table->timestamps(); $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'sfb_account_resource_unique'); $table->index(['switch_account_id', 'name'], 'sfb_account_name_index');
        });
        Schema::create('switch_faxes', function (Blueprint $table): void {
            $table->ulid('fax_id')->primary(); $table->uuid('id')->unique();
            $table->foreignUlid('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->foreignUlid('switch_fax_box_id')->nullable()->references('fax_box_id')->on('switch_fax_boxes')->nullOnDelete();
            $table->foreignUlid('switch_extension_id')->nullable()->references('extension_id')->on('switch_extensions')->nullOnDelete();
            $table->string('switch_resource_id'); $table->string('fax_box_switch_resource_id')->nullable(); $table->string('owner_switch_resource_id')->nullable();
            $table->string('folder', 16); $table->string('status', 48)->nullable(); $table->string('from_name', 128)->nullable(); $table->string('from_number', 64)->nullable(); $table->string('to_name', 128)->nullable(); $table->string('to_number', 64)->nullable(); $table->string('subject', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0); $table->unsignedTinyInteger('retries')->default(1); $table->boolean('successful')->nullable(); $table->text('error_message')->nullable();
            $table->unsignedInteger('pages')->default(0); $table->unsignedInteger('fax_speed')->default(0); $table->unsignedInteger('elapsed_seconds')->default(0); $table->timestamp('switch_created_at')->nullable();
            $table->boolean('has_document')->default(false); $table->string('document_content_type', 128)->nullable(); $table->unsignedBigInteger('document_size')->nullable();
            $table->timestamp('last_synced_at')->nullable(); $table->string('sync_status', 20)->default('stale'); $table->unsignedInteger('projection_version')->default(1); $table->json('switch_json')->nullable(); $table->timestamps(); $table->softDeletes();
            $table->unique(['switch_account_id', 'folder', 'switch_resource_id'], 'sfx_account_folder_resource_unique');
            $table->index(['switch_account_id', 'folder', 'switch_created_at'], 'sfx_account_folder_created_index'); $table->index(['switch_account_id', 'status'], 'sfx_account_status_index');
        });
    }
    public function down(): void { Schema::dropIfExists('switch_faxes'); Schema::dropIfExists('switch_fax_boxes'); }
};
