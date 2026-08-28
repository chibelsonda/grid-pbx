<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SwitchAccount>
 */
class SwitchAccountFactory extends Factory
{
    protected $model = SwitchAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'switch_account_id' => fake()->unique()->regexify('[a-f0-9]{32}'),
            'name' => fake()->company().' PBX',
            'realm' => fake()->unique()->domainName(),
            'is_enabled' => true,
        ];
    }
}
