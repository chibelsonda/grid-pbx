<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchTemporalRuleSet> */
class SwitchTemporalRuleSetFactory extends Factory
{
    protected $model = SwitchTemporalRuleSet::class;

    public function definition(): array
    {
        return ['switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->uuid(), 'name' => fake()->words(2, true), 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => []];
    }
}
