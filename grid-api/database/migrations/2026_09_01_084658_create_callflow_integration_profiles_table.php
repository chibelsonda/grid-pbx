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
        Schema::create('callflow_integration_profiles', function (Blueprint $table): void {
            $table->bigIncrements('callflow_integration_profile_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->cascadeOnDelete();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
            $table->string('integration_type', 32);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->text('settings');
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['switch_account_id', 'integration_type', 'is_active'],
                'callflow_integration_profiles_account_type_active_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callflow_integration_profiles');
    }
};
