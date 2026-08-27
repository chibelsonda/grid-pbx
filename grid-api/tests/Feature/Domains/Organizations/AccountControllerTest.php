<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use App\Domains\Organizations\Infrastructure\Models\Organization;
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
        $visible = KazooAccount::factory()->for($organization)->create(['name' => 'Visible PBX']);
        KazooAccount::factory()->create(['name' => 'Hidden PBX']);

        $this->actingAs($user)->getJson('/api/v1/accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->getKey())
            ->assertJsonMissing(['name' => 'Hidden PBX']);
    }
}
