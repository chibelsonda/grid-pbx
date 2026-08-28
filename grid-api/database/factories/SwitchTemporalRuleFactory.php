<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchTemporalRule> */
class SwitchTemporalRuleFactory extends Factory
{
    protected $model = SwitchTemporalRule::class;

    public function definition(): array
    {
        return ['switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->uuid(), 'name' => fake()->words(2, true), 'cycle' => 'weekly', 'interval' => 1, 'time_window_start' => 32400, 'time_window_stop' => 61200, 'enabled' => true, 'weekdays' => ['monday', 'tuesday'], 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => []];
    }
}
