<?php

namespace Database\Factories;

use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchGroup> */
class SwitchGroupFactory extends Factory
{
    protected $model = SwitchGroup::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->unique()->regexify('[a-f0-9]{32}'),
            'name' => fake()->words(2, true), 'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => [],
        ];
    }
}
