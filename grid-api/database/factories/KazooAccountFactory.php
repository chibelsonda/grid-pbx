<?php

namespace Database\Factories;

use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use App\Domains\Organizations\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KazooAccount>
 */
class KazooAccountFactory extends Factory
{
    protected $model = KazooAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'kazoo_account_id' => fake()->unique()->regexify('[a-f0-9]{32}'),
            'name' => fake()->company().' PBX',
            'realm' => fake()->unique()->domainName(),
            'is_enabled' => true,
        ];
    }
}
