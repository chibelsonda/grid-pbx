<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('switch_call_geography_aggregates', function (Blueprint $table): void {
            $table->bigIncrements('call_geography_aggregate_id');
            $table->uuid('id')->unique();
            $table->foreignId('switch_account_id')
                ->references('account_id')
                ->on('switch_accounts')
                ->cascadeOnDelete();
            $table->timestamp('bucket_started_at');
            $table->string('location_key', 64);
            $table->string('locality')->nullable();
            $table->string('region_code', 32)->nullable();
            $table->char('country_code', 2);
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('precision', 24)->default('numbering_plan');
            $table->unsignedInteger('inbound_count')->default(0);
            $table->unsignedInteger('outbound_count')->default(0);
            $table->string('source', 64);
            $table->timestamp('source_updated_at');
            $table->timestamps();

            $table->unique(
                ['switch_account_id', 'bucket_started_at', 'location_key', 'source'],
                'switch_call_geography_account_bucket_location_unique',
            );
            $table->index(
                ['switch_account_id', 'source', 'bucket_started_at'],
                'switch_call_geography_account_source_bucket_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('switch_call_geography_aggregates');
    }
};
