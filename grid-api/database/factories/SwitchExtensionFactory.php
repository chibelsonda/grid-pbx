<?php

namespace Database\Factories;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SwitchExtension>
 */
class SwitchExtensionFactory extends Factory
{
    protected $model = SwitchExtension::class;

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
            'username' => fake()->unique()->userName(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'display_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'extension' => (string) fake()->unique()->numberBetween(1000, 9999),
            'timezone' => fake()->timezone(),
            'is_enabled' => true,
            'source_revision' => fake()->uuid(),
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
        ];
    }
}
