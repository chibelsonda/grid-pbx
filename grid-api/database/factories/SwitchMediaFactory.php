<?php

namespace Database\Factories;

use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SwitchMedia>
 */
class SwitchMediaFactory extends Factory
{
    protected $model = SwitchMedia::class;

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
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(4),
            'language' => 'en-us',
            'media_source' => 'upload',
            'content_type' => 'audio/mpeg',
            'content_length' => fake()->numberBetween(1000, 5000000),
            'streamable' => true,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
            'switch_json' => [],
        ];
    }
}
