<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchRecording> */
class SwitchRecordingFactory extends Factory
{
    protected $model = SwitchRecording::class;
    public function definition(): array { return ['switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => '202608-'.fake()->uuid(), 'call_id' => fake()->uuid(), 'direction' => 'inbound', 'caller_id_number' => '+15550001000', 'callee_id_number' => '+15550002000', 'started_at' => now(), 'duration_seconds' => 42, 'duration_milliseconds' => 42000, 'content_type' => 'audio/mpeg', 'has_audio' => true, 'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => []]; }
}
