<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('organizationRoles')]
    public function test_account_settings_management_matches_the_organization_role(
        OrganizationRole $role,
        bool $expected,
    ): void {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);
        $account = SwitchAccount::factory()->for($organization)->create();

        $this->assertSame($expected, Gate::forUser($user)->allows('update', $account));
        $this->assertSame($expected, Gate::forUser($user)->allows('refresh', $account));
        $this->assertSame($expected, Gate::forUser($user)->allows('setStatus', $account));
    }

    public function test_user_outside_the_organization_cannot_manage_account_settings(): void
    {
        $user = User::factory()->create();
        $account = SwitchAccount::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('update', $account));
        $this->assertFalse(Gate::forUser($user)->allows('refresh', $account));
        $this->assertFalse(Gate::forUser($user)->allows('setStatus', $account));
    }

    /** @return array<string, array{OrganizationRole, bool}> */
    public static function organizationRoles(): array
    {
        return [
            'platform administrator' => [OrganizationRole::PlatformAdministrator, true],
            'reseller administrator' => [OrganizationRole::ResellerAdministrator, true],
            'account administrator' => [OrganizationRole::AccountAdministrator, true],
            'account operator' => [OrganizationRole::AccountOperator, false],
            'read-only user' => [OrganizationRole::ReadOnlyUser, false],
        ];
    }
}
