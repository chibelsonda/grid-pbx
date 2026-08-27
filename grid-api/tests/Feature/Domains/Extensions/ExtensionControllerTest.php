<?php

namespace Tests\Feature\Domains\Extensions;

use App\Domains\Extensions\Infrastructure\Models\KazooExtension;
use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use App\Domains\Organizations\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExtensionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_and_searches_projected_extensions_for_an_accessible_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        KazooExtension::factory()->for($account)->create([
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        KazooExtension::factory()->for($account)->create([
            'display_name' => 'Bob Support',
            'extension' => '1002',
        ]);

        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->getKey()}/extensions?search=Alice")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.extension', '1001');
    }

    public function test_it_hides_extensions_from_users_outside_the_account_organization(): void
    {
        $user = User::factory()->create();
        $account = KazooAccount::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->getKey()}/extensions")
            ->assertNotFound();
    }

    /** @return array{User, KazooAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);

        return [$user, KazooAccount::factory()->for($organization)->create()];
    }
}
