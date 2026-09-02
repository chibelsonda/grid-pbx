<?php

namespace Tests\Feature\Domains\Groups;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Groups\Contracts\SwitchGroupGateway;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GroupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_group_with_typed_members_and_music_on_hold(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $device = SwitchDevice::factory()->for($account)->create(['switch_resource_id' => 'switch-device-1']);
        $media = SwitchMedia::factory()->for($account)->create(['switch_resource_id' => 'switch-media-1']);
        $gateway = $this->mock(SwitchGroupGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(function (SwitchAccount $received, array $data) use ($account): bool {
            return $received->is($account)
                && $data['switch_music_on_hold_media_id'] === 'switch-media-1'
                && array_column($data['resolved_members'], 'switch_resource_id') === ['switch-user-1', 'switch-device-1'];
        })->andReturn([
            'id' => 'switch-group-1', 'name' => 'Support',
            'music_on_hold' => ['media_id' => 'switch-media-1'],
            'endpoints' => [
                'switch-user-1' => ['type' => 'user', 'weight' => 1],
                'switch-device-1' => ['type' => 'device', 'weight' => 2],
            ],
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/groups", [
            'name' => 'Support', 'music_on_hold_media_id' => $media->id,
            'members' => [
                ['type' => 'user', 'id' => $extension->id, 'weight' => 1],
                ['type' => 'device', 'id' => $device->id, 'weight' => 2],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Support')
            ->assertJsonPath('data.members.0.target.id', $extension->id)
            ->assertJsonPath('data.music_on_hold_media.id', $media->id)
            ->assertJsonMissingPath('data.group_id')->assertJsonMissingPath('data.switch_json');
        $this->assertDatabaseHas('switch_groups', ['id' => $response->json('data.id'), 'switch_resource_id' => 'switch-group-1']);
        $this->assertDatabaseCount('switch_group_members', 2);
    }

    public function test_read_only_user_cannot_mutate_and_cross_tenant_member_is_rejected(): void
    {
        $this->mock(SwitchGroupGateway::class)->shouldNotReceive('create');
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/groups", ['name' => 'Blocked', 'members' => []])->assertForbidden();

        [$operator, $managed] = $this->accessibleAccount();
        $foreign = SwitchDevice::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/groups", [
            'name' => 'Invalid', 'members' => [['type' => 'device', 'id' => $foreign->id, 'weight' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('members');
    }

    public function test_update_preserves_hidden_switch_fields_and_rejects_operator_owned_metadata(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
        ]);
        $group = SwitchGroup::factory()->for($account)->create([
            'switch_resource_id' => 'switch-group-1',
            'switch_json' => [
                'id' => 'switch-group-1',
                'name' => 'Support',
                'endpoints' => [
                    'switch-user-1' => [
                        'type' => 'user',
                        'weight' => 9,
                        'vendor_alert' => ['enabled' => true],
                    ],
                ],
                'music_on_hold' => [
                    'media_id' => 'switch-media-old',
                    'vendor_mode' => 'shuffle',
                ],
                'flags' => ['external-managed'],
                'future_option' => ['nested' => 'keep'],
                'pvt_secret' => 'discard',
                'redacted_option' => '[REDACTED]',
            ],
        ]);
        $gateway = $this->mock(SwitchGroupGateway::class);
        $gateway->shouldReceive('update')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $data): bool => $received->is($account)
                && $resourceId === 'switch-group-1'
                && $data['switch_flags'] === ['external-managed']
                && $data['resolved_members'][0]['switch_resource_id'] === 'switch-user-1'
                && $data['switch_preserved_options'] === [
                    'future_option' => ['nested' => 'keep'],
                    'endpoints' => [
                        'switch-user-1' => ['vendor_alert' => ['enabled' => true]],
                    ],
                    'music_on_hold' => ['vendor_mode' => 'shuffle'],
                ],
        )->andReturn([
            'id' => 'switch-group-1',
            'name' => 'Updated support',
            'endpoints' => [
                'switch-user-1' => [
                    'type' => 'user',
                    'weight' => 1,
                    'vendor_alert' => ['enabled' => true],
                ],
            ],
            'music_on_hold' => ['vendor_mode' => 'shuffle'],
            'flags' => ['external-managed'],
            'future_option' => ['nested' => 'keep'],
        ]);

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}/groups/{$group->id}", [
            'name' => 'Updated support',
            'music_on_hold_media_id' => null,
            'members' => [[
                'type' => 'user',
                'id' => $extension->id,
                'weight' => 1,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated support')
            ->assertJsonPath('data.members.0.target.id', $extension->id)
            ->assertJsonMissingPath('data.switch_preserved_options')
            ->assertDontSee('switch-user-1')
            ->assertDontSee('vendor_alert');

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}/groups/{$group->id}", [
            'name' => 'Rejected flags',
            'music_on_hold_media_id' => null,
            'members' => [],
            'flags' => ['operator-managed'],
        ])->assertUnprocessable()->assertJsonValidationErrors('flags');

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}/groups/{$group->id}", [
            'name' => 'Rejected endpoint metadata',
            'music_on_hold_media_id' => null,
            'members' => [[
                'type' => 'user',
                'id' => $extension->id,
                'weight' => 1,
                'switch_resource_id' => 'operator-controlled',
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('members.0');
    }

    public function test_accessible_user_lists_only_account_groups(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $group = SwitchGroup::factory()->for($account)->create(['name' => 'Support']);
        SwitchGroup::factory()->create();
        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/groups")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $group->id)
            ->assertJsonMissingPath('data.0.switch_resource_id');
    }

    public function test_accessible_user_views_group_options_and_account_scoped_detail(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $extension = SwitchExtension::factory()->for($account)->create(['display_name' => 'Ada Lovelace']);
        $device = SwitchDevice::factory()->for($account)->create(['name' => 'Reception phone']);
        $media = SwitchMedia::factory()->for($account)->create(['name' => 'Main hold music']);
        $group = SwitchGroup::factory()->for($account)->create([
            'name' => 'Support',
            'switch_resource_id' => 'private-group-id',
            'switch_json' => ['private' => 'server-only'],
        ]);
        $foreign = SwitchGroup::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/groups/options")
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $extension->id)
            ->assertJsonPath('data.devices.0.id', $device->id)
            ->assertJsonPath('data.groups.0.id', $group->id)
            ->assertJsonPath('data.media.0.id', $media->id)
            ->assertJsonMissingPath('data.users.0.switch_resource_id');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $group->id)
            ->assertJsonPath('data.name', 'Support')
            ->assertJsonMissing(['private-group-id', 'server-only']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/groups/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_operator_deletes_an_unreferenced_group(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $group = SwitchGroup::factory()->for($account)->create([
            'switch_resource_id' => 'switch-group-delete',
        ]);
        $this->mock(SwitchGroupGateway::class)
            ->shouldReceive('delete')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $resourceId): bool => $received->is($account)
                && $resourceId === 'switch-group-delete');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/groups/{$group->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($group);
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.deleted']);
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
