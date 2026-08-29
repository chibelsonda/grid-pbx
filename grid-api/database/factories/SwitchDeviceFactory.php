<?php

namespace Database\Factories;

use App\Domains\Devices\Enums\DeviceRegistrationStatus;
use App\Domains\Devices\Enums\ProvisioningEnrollmentStatus;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SwitchDevice>
 */
class SwitchDeviceFactory extends Factory
{
    protected $model = SwitchDevice::class;

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
            'name' => fake()->words(2, true).' phone',
            'device_type' => 'sip_device',
            'make' => 'Acme',
            'model' => 'Desk 100',
            'mac_address' => fake()->macAddress(),
            'is_enabled' => true,
            'registration_status' => DeviceRegistrationStatus::Unknown,
            'registration_checked_at' => null,
            'provisioning_enrollment_status' => ProvisioningEnrollmentStatus::NotEnrolled,
            'provisioning_enrollment_provider' => null,
            'provisioning_enrolled_at' => null,
            'provisioning_detached_at' => null,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
        ];
    }
}
