<?php

namespace Tests\Unit\Domains\Organizations;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, bool}> */
    public static function onboardingRoles(): array
    {
        return [
            'platform administrator' => ['platform_administrator', true],
            'reseller administrator' => ['reseller_administrator', true],
            'account administrator' => ['account_administrator', false],
            'account operator' => ['account_operator', false],
            'read-only user' => ['read_only_user', false],
        ];
    }

    #[DataProvider('onboardingRoles')]
    public function test_allows_descendant_onboarding_only_for_platform_and_reseller_administrators(
        string $role,
        bool $expected,
    ): void {
        $actor = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($actor, ['role' => $role]);
        $account = SwitchAccount::factory()->for($organization)->create(['is_reseller' => true]);

        $this->assertSame($expected, $actor->can('onboardDescendant', $account));
    }
}
