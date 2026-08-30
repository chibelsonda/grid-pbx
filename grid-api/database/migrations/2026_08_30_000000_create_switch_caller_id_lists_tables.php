<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_caller_id_lists', function (Blueprint $table): void {
            $table->bigIncrements('caller_id_list_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')->references('account_id')->on('switch_accounts')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('name', 128);
            $table->string('description', 128)->nullable();
            $table->string('organization')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 20)->default('stale');
            $table->unsignedInteger('projection_version')->default(1);
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['switch_account_id', 'switch_resource_id'], 'scil_account_resource_unique');
            $table->index(['switch_account_id', 'name'], 'scil_account_name_index');
        });

        Schema::create('switch_caller_id_list_entries', function (Blueprint $table): void {
            $table->bigIncrements('caller_id_list_entry_id');
            $table->uuid('id')->unique();
            $table->unsignedBigInteger('switch_caller_id_list_id');
            $table->foreign('switch_caller_id_list_id', 'scile_list_fk')
                ->references('caller_id_list_id')->on('switch_caller_id_lists')->cascadeOnDelete();
            $table->string('switch_resource_id');
            $table->string('display_name', 128)->nullable();
            $table->string('number')->nullable();
            $table->string('pattern')->nullable();
            $table->json('switch_json')->nullable();
            $table->timestamps();
            $table->unique(['switch_caller_id_list_id', 'switch_resource_id'], 'scile_list_resource_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_caller_id_list_entries');
        Schema::dropIfExists('switch_caller_id_lists');
    }
};
