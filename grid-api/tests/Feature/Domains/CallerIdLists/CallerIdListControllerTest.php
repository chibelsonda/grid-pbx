<?php

namespace Tests\Feature\Domains\CallerIdLists;

use App\Domains\CallerIdLists\Contracts\SwitchCallerIdListGateway;
use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CallerIdListControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accessible_user_lists_and_views_only_account_caller_id_lists(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $list = SwitchCallerIdList::query()->create([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => 'private-list-id',
            'name' => 'VIP callers',
            'switch_json' => ['private' => 'server-only'],
        ]);
        $entry = $list->entries()->create([
            'switch_resource_id' => 'private-entry-id',
            'display_name' => 'Support',
            'number' => '+15550001000',
            'switch_json' => ['private' => 'entry-server-only'],
        ]);
        $foreign = SwitchCallerIdList::query()->create([
            'switch_account_id' => SwitchAccount::factory()->create()->getKey(),
            'switch_resource_id' => 'foreign-list-id',
            'name' => 'Foreign list',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/caller-id-lists?search=VIP")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $list->id)
            ->assertJsonPath('data.0.entry_count', 1)
            ->assertJsonMissing(['private-list-id', 'server-only']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/caller-id-lists/{$list->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $list->id)
            ->assertJsonPath('data.entries.0.id', $entry->id)
            ->assertJsonPath('data.entries.0.number', '+15550001000')
            ->assertJsonMissing(['private-entry-id', 'entry-server-only']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/caller-id-lists/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_it_creates_updates_and_deletes_lists_using_only_public_entry_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchCallerIdListGateway::class);
        $gateway->shouldReceive('create')->once()->andReturn(['id' => 'switch-list-vip', 'name' => 'VIP callers']);
        $gateway->shouldReceive('createEntry')->once()->andReturn([
            'id' => 'switch-entry-1',
            'list_id' => 'switch-list-vip',
            'number' => '+1555',
        ]);
        $gateway->shouldReceive('details')->once()->andReturn($this->snapshot('+1555'));

        $created = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/caller-id-lists", [
                'name' => 'VIP callers',
                'description' => 'Priority inbound callers',
                'organization' => null,
                'entries' => [[
                    'id' => null,
                    'display_name' => 'Support prefix',
                    'number' => '+1555',
                    'pattern' => null,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'VIP callers')
            ->assertJsonPath('data.entries.0.number', '+1555')
            ->assertJsonMissing(['switch-list-vip', 'switch-entry-1']);
        $listId = $created->json('data.id');
        $entryId = $created->json('data.entries.0.id');

        $gateway->shouldReceive('update')->once()->andReturn(['id' => 'switch-list-vip', 'name' => 'Priority callers']);
        $gateway->shouldReceive('updateEntry')->once()->withArgs(fn (
            SwitchAccount $receivedAccount,
            string $listResourceId,
            string $entryResourceId,
            array $entry,
        ): bool => $receivedAccount->is($account)
            && $listResourceId === 'switch-list-vip'
            && $entryResourceId === 'switch-entry-1'
            && $entry['id'] === $entryId
            && $entry['pattern'] === '^\\+632')->andReturn([
                'id' => 'switch-entry-1',
                'list_id' => 'switch-list-vip',
                'pattern' => '^\\+632',
            ]);
        $gateway->shouldReceive('details')->once()->andReturn($this->snapshot(null, '^\\+632', 'Priority callers'));

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/caller-id-lists/{$listId}", [
                'name' => 'Priority callers',
                'description' => null,
                'organization' => 'GridPBX',
                'entries' => [[
                    'id' => $entryId,
                    'display_name' => 'Manila pattern',
                    'number' => null,
                    'pattern' => '^\\+632',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.entries.0.id', $entryId)
            ->assertJsonPath('data.entries.0.pattern', '^\\+632')
            ->assertJsonMissing(['switch-list-vip', 'switch-entry-1']);

        $gateway->shouldReceive('delete')->once()->withArgs(fn (
            SwitchAccount $receivedAccount,
            string $resourceId,
        ): bool => $receivedAccount->is($account) && $resourceId === 'switch-list-vip');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/caller-id-lists/{$listId}")
            ->assertNoContent();
    }

    public function test_it_rejects_ambiguous_or_unsafe_entries(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/caller-id-lists", [
                'name' => 'Unsafe',
                'description' => null,
                'organization' => null,
                'entries' => [[
                    'id' => null,
                    'display_name' => null,
                    'number' => '+1555',
                    'pattern' => '(?R)',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entries.0.number', 'entries.0.pattern']);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->getKey(), [
            'role' => OrganizationRole::AccountOperator->value,
        ]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }

    /** @return array{list: array<string, mixed>, entries: list<array<string, mixed>>} */
    private function snapshot(?string $number, ?string $pattern = null, string $name = 'VIP callers'): array
    {
        return [
            'list' => ['id' => 'switch-list-vip', 'name' => $name],
            'entries' => [[
                'id' => 'switch-entry-1',
                'list_id' => 'switch-list-vip',
                'displayname' => $pattern === null ? 'Support prefix' : 'Manila pattern',
                'number' => $number,
                'pattern' => $pattern,
            ]],
        ];
    }
}
