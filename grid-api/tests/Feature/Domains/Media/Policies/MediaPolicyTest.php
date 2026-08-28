<?php

namespace Tests\Feature\Domains\Media\Policies;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Media\Policies\MediaPolicy;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('roles')]
    public function test_each_accessible_role_has_the_expected_media_permissions(
        OrganizationRole $role,
        bool $canManage,
    ): void {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);
        $account = SwitchAccount::factory()->for($organization)->create();
        $media = SwitchMedia::factory()->for($account)->create();
        $policy = $this->app->make(MediaPolicy::class);

        $this->assertTrue($policy->viewAny($user, $account));
        $this->assertTrue($policy->view($user, $media, $account));
        $this->assertSame($canManage, $policy->create($user, $account));
        $this->assertSame($canManage, $policy->update($user, $media, $account));
        $this->assertSame($canManage, $policy->delete($user, $media, $account));
        $this->assertSame($canManage, $policy->sync($user, $account));
        $this->assertSame($canManage, $policy->updateMusicOnHold($user, $account));
    }

    /** @return array<string, array{OrganizationRole, bool}> */
    public static function roles(): array
    {
        return [
            'platform administrator' => [OrganizationRole::PlatformAdministrator, true],
            'reseller administrator' => [OrganizationRole::ResellerAdministrator, true],
            'account administrator' => [OrganizationRole::AccountAdministrator, true],
            'account operator' => [OrganizationRole::AccountOperator, true],
            'read-only user' => [OrganizationRole::ReadOnlyUser, false],
        ];
    }
}
