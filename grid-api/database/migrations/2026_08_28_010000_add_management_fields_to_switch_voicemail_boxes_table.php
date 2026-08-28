<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switch_voicemail_boxes', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->after('mailbox');
            $table->json('notification_emails')->nullable()->after('timezone');
            $table->boolean('transcribe')->default(false)->after('notification_emails');
            $table->boolean('require_pin')->default(false)->after('transcribe');
        });
    }

    public function down(): void
    {
        Schema::table('switch_voicemail_boxes', function (Blueprint $table): void {
            $table->dropColumn(['timezone', 'notification_emails', 'transcribe', 'require_pin']);
        });
    }
};
