<?php

namespace Database\Factories;

use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchConference> */
class SwitchConferenceFactory extends Factory
{
    protected $model = SwitchConference::class;
    public function definition(): array
    {
        return [
            'switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->uuid(), 'name' => fake()->words(2, true),
            'member_pin_configured' => false, 'moderator_pin_configured' => false, 'member_join_muted' => true,
            'member_join_deaf' => false, 'member_play_entry_prompt' => false, 'moderator_join_muted' => false,
            'moderator_join_deaf' => false, 'play_name' => false, 'play_welcome' => true,
            'require_moderator' => false, 'wait_for_moderator' => false, 'active_members' => 0,
            'active_moderators' => 0, 'duration_seconds' => 0, 'is_locked' => false,
            'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => [],
        ];
    }
}
