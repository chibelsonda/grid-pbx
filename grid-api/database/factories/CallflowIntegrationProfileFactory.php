<?php

namespace Database\Factories;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallflowIntegrationProfile>
 */
class CallflowIntegrationProfileFactory extends Factory
{
    protected $model = CallflowIntegrationProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'switch_account_id' => SwitchAccount::factory(),
            'integration_type' => CallflowIntegrationType::Pivot,
            'name' => fake()->words(2, true),
            'is_active' => true,
            'settings' => [
                'voice_url' => 'https://'.fake()->unique()->domainName().'/pivot',
                'methods' => ['post'],
                'formats' => ['kazoo'],
                'req_body_format' => 'json',
                'req_timeout_ms' => 5000,
                'custom_request_headers' => [],
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function globalCarrier(): static
    {
        return $this->state(fn (): array => [
            'integration_type' => CallflowIntegrationType::GlobalCarrier,
            'settings' => [],
        ]);
    }

    public function disa(): static
    {
        return $this->state(fn (): array => [
            'integration_type' => CallflowIntegrationType::Disa,
            'settings' => [
                'pin' => '82736491',
                'retries' => 2,
                'interdigit_ms' => 3000,
                'max_digits' => 15,
                'preconnect_audio' => 'dialtone',
            ],
        ]);
    }

    public function accountCarrier(string $scope = 'account'): static
    {
        return $this->state(fn (): array => [
            'integration_type' => CallflowIntegrationType::AccountCarrier,
            'settings' => ['scope' => $scope],
        ]);
    }
}
