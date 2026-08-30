<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('switch_devices', function (Blueprint $table): void {
            $table->string('endpoint_family')->nullable()->after('make');
        });

        Schema::create('switch_line_keys', function (Blueprint $table): void {
            $table->bigIncrements('line_key_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_device_id')->references('device_id')->on('switch_devices')->cascadeOnDelete();
            $table->string('category', 16);
            $table->unsignedSmallInteger('position');
            $table->string('type', 32);
            $table->string('label')->nullable();
            $table->string('value')->nullable();
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['switch_device_id', 'category', 'position'], 'slk_device_category_position_unique');
            $table->index(['category', 'type'], 'slk_category_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_line_keys');
        Schema::table('switch_devices', function (Blueprint $table): void {
            $table->dropColumn('endpoint_family');
        });
    }
};
