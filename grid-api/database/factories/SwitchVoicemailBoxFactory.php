<?php

namespace Database\Factories;

use App\Domains\Extensions\Models\SwitchVoicemailBox;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SwitchVoicemailBox>
 */
class SwitchVoicemailBoxFactory extends Factory
{
    protected $model = SwitchVoicemailBox::class;

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
            'name' => fake()->name().' voicemail',
            'mailbox' => (string) fake()->unique()->numberBetween(1000, 9999),
            'is_setup' => true,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
        ];
    }
}
