<?php

namespace Tests\Feature\Domains\Directories;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Directories\Contracts\SwitchDirectoryGateway;
use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Directories\Services\DirectoryMutationService;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DirectoryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accessible_user_lists_only_account_directories_without_internal_identifiers(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $directory = SwitchDirectory::factory()->for($account)->create(['name' => 'People']);
        SwitchDirectory::factory()->create(['name' => 'Other tenant']);

        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/directories")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $directory->id)
            ->assertJsonMissingPath('data.0.directory_id')->assertJsonMissingPath('data.0.switch_resource_id')
            ->assertJsonMissingPath('data.0.switch_json');
    }

    public function test_accessible_user_views_directory_options_and_account_scoped_detail(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $extension = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Ada Lovelace',
            'extension' => '1001',
        ]);
        SwitchCallflow::factory()->for($account)->for($extension, 'extension')->create(['name' => 'Ada route']);
        $directory = SwitchDirectory::factory()->for($account)->create([
            'name' => 'People',
            'switch_resource_id' => 'private-directory-id',
            'switch_json' => ['private' => 'server-only'],
        ]);
        $foreign = SwitchDirectory::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/directories/options")
            ->assertOk()
            ->assertJsonCount(1, 'data.extensions')
            ->assertJsonPath('data.extensions.0.id', $extension->id)
            ->assertJsonPath('data.extensions.0.label', 'Ada Lovelace')
            ->assertJsonMissingPath('data.extensions.0.switch_resource_id');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/directories/{$directory->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $directory->id)
            ->assertJsonPath('data.name', 'People')
            ->assertJsonMissing(['private-directory-id', 'server-only']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/directories/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_operator_creates_directory_and_resolves_members_server_side(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        SwitchCallflow::factory()->for($account)->for($extension, 'extension')->create(['switch_resource_id' => 'switch-callflow-1']);
        $gateway = $this->mock(SwitchDirectoryGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(
            fn (SwitchAccount $received, array $data): bool => $received->is($account)
                && $data['flags'] === [],
        )->andReturn(['id' => 'switch-directory-1', 'name' => 'People', 'flags' => []]);
        $gateway->shouldReceive('replaceMembers')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $members): bool => $received->is($account)
                && $resourceId === 'switch-directory-1'
                && $members === ['switch-user-1' => 'switch-callflow-1'],
        )->andReturn([
            'id' => 'switch-directory-1', 'name' => 'People', 'confirm_match' => true,
            'min_dtmf' => 3, 'max_dtmf' => 0, 'sort_by' => 'last_name',
            'flags' => [],
            'users' => [['user_id' => 'switch-user-1', 'callflow_id' => 'switch-callflow-1']],
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/directories", [
            'name' => 'People', 'confirm_match' => true, 'min_dtmf' => 3,
            'max_dtmf' => 0, 'sort_by' => 'last_name',
            'member_ids' => [$extension->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'People')
            ->assertJsonPath('data.flags', [])
            ->assertJsonPath('data.members.0.extension.id', $extension->id)
            ->assertJsonMissingPath('data.members.0.switch_user_resource_id');
        $this->assertDatabaseHas('switch_directories', ['id' => $response->json('data.id'), 'switch_resource_id' => 'switch-directory-1']);
        $this->assertDatabaseHas('switch_directory_members', ['switch_user_resource_id' => 'switch-user-1']);
        $this->assertSame([], SwitchDirectory::query()->firstOrFail()->switch_json['flags']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'directory.created']);
    }

    public function test_operator_updates_and_deletes_an_unreferenced_directory(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $directory = SwitchDirectory::factory()->for($account)->create([
            'switch_resource_id' => 'switch-directory-1',
            'name' => 'Old people',
            'switch_json' => ['id' => 'switch-directory-1', 'flags' => []],
        ]);
        $snapshot = [
            'id' => 'switch-directory-1',
            'name' => 'Updated people',
            'confirm_match' => true,
            'min_dtmf' => 3,
            'max_dtmf' => 0,
            'sort_by' => 'last_name',
            'flags' => [],
            'users' => [],
        ];
        $gateway = $this->mock(SwitchDirectoryGateway::class);
        $gateway->shouldReceive('update')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $data): bool => $received->is($account)
                && $resourceId === 'switch-directory-1'
                && $data['name'] === 'Updated people',
        )->andReturn($snapshot);
        $gateway->shouldReceive('replaceMembers')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $members): bool => $received->is($account)
                && $resourceId === 'switch-directory-1'
                && $members === [],
        )->andReturn($snapshot);

        $this->actingAs($user)
            ->putJson(
                "/api/v1/accounts/{$account->id}/directories/{$directory->id}",
                $this->payload([], ['name' => 'Updated people']),
            )
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated people');

        $gateway->shouldReceive('replaceMembers')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $members): bool => $received->is($account)
                && $resourceId === 'switch-directory-1'
                && $members === [],
        )->andReturn($snapshot);
        $gateway->shouldReceive('delete')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId): bool => $received->is($account)
                && $resourceId === 'switch-directory-1',
        );

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/directories/{$directory->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($directory);
        $this->assertDatabaseHas('audit_logs', ['action' => 'directory.deleted']);
    }

    public function test_externally_owned_flags_are_rejected_before_switch_mutation(): void
    {
        [$operator, $account] = $this->accessibleAccount();
        $this->mock(SwitchDirectoryGateway::class)->shouldNotReceive('create');

        $this->actingAs($operator)
            ->postJson("/api/v1/accounts/{$account->id}/directories", $this->payload([], [
                'flags' => ['operator-replacement'],
                'preserved_options' => ['future_option' => 'operator-replacement'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flags', 'preserved_options']);
    }

    public function test_member_failure_restores_the_previous_directory_flags(): void
    {
        [$operator, $account] = $this->accessibleAccount();
        $directory = SwitchDirectory::factory()->for($account)->create([
            'switch_resource_id' => 'switch-directory-1',
            'switch_json' => [
                'id' => 'switch-directory-1',
                'flags' => ['stable'],
                'users' => [['user_id' => 'private-user-id']],
                'future_option' => ['nested' => 'keep', 'secret' => '[REDACTED]'],
                'pvt_private' => 'drop',
            ],
        ]);
        $gateway = $this->mock(SwitchDirectoryGateway::class);
        $gateway->shouldReceive('update')->once()->ordered()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $data): bool => $received->is($account)
                && $resourceId === 'switch-directory-1'
                && $data['flags'] === ['stable']
                && $data['preserved_options'] === ['future_option' => ['nested' => 'keep']],
        )->andReturn(['id' => 'switch-directory-1', 'name' => 'People']);
        $gateway->shouldReceive('replaceMembers')->once()->ordered()
            ->andThrow(new \RuntimeException('Member mapping failed.'));
        $gateway->shouldReceive('update')->once()->ordered()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $data): bool => $received->is($account)
                && $resourceId === 'switch-directory-1'
                && $data['flags'] === ['stable']
                && $data['preserved_options'] === ['future_option' => ['nested' => 'keep']],
        )->andReturn(['id' => 'switch-directory-1', 'name' => 'People']);

        $this->expectException(\RuntimeException::class);

        $this->app->make(DirectoryMutationService::class)->update(
            $account,
            $directory,
            $operator,
            [...$this->payload([]), 'flags' => ['replacement']],
        );
    }

    public function test_read_only_user_cannot_create_and_cross_tenant_member_is_rejected(): void
    {
        $this->mock(SwitchDirectoryGateway::class)->shouldNotReceive('create');
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/directories", $this->payload([]))->assertForbidden();

        [$operator, $managed] = $this->accessibleAccount();
        $foreign = SwitchExtension::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/directories", $this->payload([$foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('member_ids');
    }

    /** @param list<string> $members
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $members, array $overrides = []): array
    {
        return array_replace([
            'name' => 'People', 'confirm_match' => true, 'min_dtmf' => 3,
            'max_dtmf' => 0, 'sort_by' => 'last_name',
            'member_ids' => $members,
        ], $overrides);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(OrganizationRole $role = OrganizationRole::AccountOperator): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
