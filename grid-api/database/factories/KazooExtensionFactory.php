<?php

namespace Database\Factories;

use App\Domains\Extensions\Infrastructure\Models\KazooExtension;
use App\Domains\KazooSynchronization\Domain\ProjectionStatus;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KazooExtension>
 */
class KazooExtensionFactory extends Factory
{
    protected $model = KazooExtension::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kazoo_account_id' => KazooAccount::factory(),
            'kazoo_resource_id' => fake()->unique()->regexify('[a-f0-9]{32}'),
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
