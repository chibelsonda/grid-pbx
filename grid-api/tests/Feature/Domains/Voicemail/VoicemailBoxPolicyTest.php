<?php

namespace Tests\Feature\Domains\Voicemail;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VoicemailBoxPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('organizationRoles')]
    public function test_voicemail_management_matches_the_organization_role(
        OrganizationRole $role,
        bool $expected,
    ): void {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);
        $account = SwitchAccount::factory()->for($organization)->create();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();

        $this->assertSame($expected, Gate::forUser($user)->allows('create', [SwitchVoicemailBox::class, $account]));
        $this->assertSame($expected, Gate::forUser($user)->allows('update', [$voicemailBox, $account]));
        $this->assertSame($expected, Gate::forUser($user)->allows('delete', [$voicemailBox, $account]));
    }

    /** @return array<string, array{OrganizationRole, bool}> */
    public static function organizationRoles(): array
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
