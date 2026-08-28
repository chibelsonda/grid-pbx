<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchVoicemailMessage> */
class SwitchVoicemailMessageFactory extends Factory
{
    protected $model = SwitchVoicemailMessage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'switch_account_id' => SwitchAccount::factory(),
            'switch_voicemail_box_id' => SwitchVoicemailBox::factory(),
            'switch_resource_id' => fake()->unique()->regexify('[a-f0-9]{39}'),
            'folder' => 'new',
            'caller_id_name' => fake()->name(),
            'caller_id_number' => fake()->phoneNumber(),
            'length' => fake()->numberBetween(5000, 120000),
            'source_timestamp' => 62167219200 + now()->timestamp,
            'occurred_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
        ];
    }
}
