<?php

namespace Database\Factories;

use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchDirectory> */
class SwitchDirectoryFactory extends Factory
{
    protected $model = SwitchDirectory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->unique()->regexify('[a-f0-9]{32}'),
            'name' => fake()->words(2, true), 'confirm_match' => true, 'min_dtmf' => 3,
            'max_dtmf' => 0, 'sort_by' => 'last_name', 'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => [],
        ];
    }
}
