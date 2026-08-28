<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchPhoneNumber> */
class SwitchPhoneNumberFactory extends Factory
{
    protected $model = SwitchPhoneNumber::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = '+1'.fake()->unique()->numerify('##########');

        return [
            'switch_account_id' => SwitchAccount::factory(),
            'number' => $number,
            'state' => 'in_service',
            'used_by' => null,
            'assigned_to_switch_account_id' => null,
            'carrier_name' => 'local',
            'features' => ['local'],
            'cnam_display_name' => null,
            'cnam_inbound_lookup' => false,
            'e911_status' => null,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
            'switch_json' => ['id' => $number, 'state' => 'in_service', 'features' => ['local']],
        ];
    }
}
