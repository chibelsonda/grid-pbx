<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchVoicemailGreeting> */
class SwitchVoicemailGreetingFactory extends Factory
{
    protected $model = SwitchVoicemailGreeting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'switch_account_id' => SwitchAccount::factory(),
            'switch_voicemail_box_id' => SwitchVoicemailBox::factory(),
            'switch_resource_id' => fake()->unique()->regexify('[a-f0-9]{32}'),
            'type' => 'unavailable',
            'name' => 'Unavailable greeting',
            'description' => 'greeting.mp3',
            'content_type' => 'audio/mpeg',
            'content_length' => 4096,
            'media_source' => 'upload',
            'streamable' => true,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
        ];
    }
}
