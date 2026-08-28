<?php

namespace Database\Factories;

use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchBlacklist> */
class SwitchBlacklistFactory extends Factory
{
    protected $model = SwitchBlacklist::class;
    public function definition(): array
    {
        return ['switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->uuid(), 'name' => fake()->words(2, true), 'should_block_anonymous' => false, 'is_active' => false, 'flags' => [], 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => []];
    }
}
