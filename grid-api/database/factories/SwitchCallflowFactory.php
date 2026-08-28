<?php

namespace Database\Factories;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SwitchCallflow>
 */
class SwitchCallflowFactory extends Factory
{
    protected $model = SwitchCallflow::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'switch_account_id' => SwitchAccount::factory(),
            'switch_resource_id' => fake()->unique()->regexify('[a-f0-9]{32}'),
            'owner_switch_resource_id' => fake()->regexify('[a-f0-9]{32}'),
            'name' => fake()->words(2, true),
            'numbers' => [(string) fake()->unique()->numberBetween(1000, 9999)],
            'patterns' => [],
            'flags' => [],
            'modules' => ['user', 'voicemail'],
            'root_module' => 'user',
            'node_count' => 2,
            'max_depth' => 2,
            'is_feature_code' => false,
            'flow_structure' => [
                'module' => 'user',
                'children' => [
                    '_' => ['module' => 'voicemail', 'children' => []],
                ],
            ],
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
        ];
    }
}
