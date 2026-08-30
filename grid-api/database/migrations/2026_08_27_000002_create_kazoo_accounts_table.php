<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kazoo_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kazoo_account_id');
            $table->string('name');
            $table->string('realm')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'kazoo_account_id']);
            $table->index('kazoo_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kazoo_accounts');
    }
};
