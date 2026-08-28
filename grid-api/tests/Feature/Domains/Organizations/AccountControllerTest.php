<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_only_lists_accounts_available_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);
        $visible = SwitchAccount::factory()->for($organization)->create(['name' => 'Visible PBX']);
        SwitchAccount::factory()->create(['name' => 'Hidden PBX']);

        $this->actingAs($user)->getJson('/api/v1/accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.organization_role', 'account_operator')
            ->assertJsonPath('data.0.permissions.can_manage_devices', true)
            ->assertJsonMissing(['name' => 'Hidden PBX']);
    }
}
