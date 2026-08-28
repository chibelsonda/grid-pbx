<?php

namespace Database\Factories;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchCallDetailRecord> */
class SwitchCallDetailRecordFactory extends Factory
{
    protected $model = SwitchCallDetailRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $resourceId = fake()->uuid();

        return [
            'switch_account_id' => SwitchAccount::factory(),
            'switch_extension_id' => null,
            'switch_resource_id' => $resourceId,
            'call_id' => fake()->uuid(),
            'interaction_id' => fake()->uuid(),
            'direction' => fake()->randomElement(['inbound', 'outbound']),
            'caller_id_name' => fake()->name(),
            'caller_id_number' => '+1'.fake()->numerify('##########'),
            'callee_id_name' => fake()->name(),
            'callee_id_number' => fake()->numerify('####'),
            'from_uri' => 'sip:caller@example.test',
            'to_uri' => 'sip:callee@example.test',
            'request_uri' => 'sip:callee@example.test',
            'started_at' => now(),
            'duration_seconds' => 60,
            'billing_seconds' => 45,
            'hangup_cause' => 'NORMAL_CLEARING',
            'disposition' => 'SUCCESS',
            'recording_available' => false,
            'last_synced_at' => now(),
            'switch_json' => [
                'id' => $resourceId,
                'direction' => 'inbound',
                'duration_seconds' => 60,
            ],
        ];
    }
}
