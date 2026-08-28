<?php

namespace Tests\Feature\Domains\CallDetailRecords;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallDetailRecords\Policies\CallDetailRecordPolicy;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CallDetailRecordPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{OrganizationRole, bool}> */
    public static function roles(): array
    {
        return [
            'platform administrator' => [OrganizationRole::PlatformAdministrator, true],
            'reseller administrator' => [OrganizationRole::ResellerAdministrator, true],
            'account administrator' => [OrganizationRole::AccountAdministrator, true],
            'account operator' => [OrganizationRole::AccountOperator, true],
            'read only user' => [OrganizationRole::ReadOnlyUser, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_mapped_roles_can_view_and_only_operating_roles_can_synchronize(
        OrganizationRole $role,
        bool $canSynchronize,
    ): void {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->getKey(), ['role' => $role->value]);
        $account = SwitchAccount::factory()->for($organization)->create();
        $record = SwitchCallDetailRecord::factory()->for($account)->create();
        $policy = $this->app->make(CallDetailRecordPolicy::class);

        $this->assertTrue($policy->viewAny($user, $account));
        $this->assertTrue($policy->view($user, $record, $account));
        $this->assertSame($canSynchronize, $policy->sync($user, $account));
    }

    public function test_record_from_another_account_is_not_viewable(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->getKey(), [
            'role' => OrganizationRole::AccountOperator->value,
        ]);
        $account = SwitchAccount::factory()->for($organization)->create();
        $otherRecord = SwitchCallDetailRecord::factory()->create();

        $this->assertFalse(
            $this->app->make(CallDetailRecordPolicy::class)->view($user, $otherRecord, $account),
        );
    }
}
