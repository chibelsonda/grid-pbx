<?php

namespace Tests\Feature\Domains\Conferences;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConferenceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_options_separate_all_media_from_streamable_audio_playback_choices(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $audio = SwitchMedia::factory()->for($account)->create([
            'name' => 'Welcome prompt',
            'content_type' => 'audio/mpeg',
            'streamable' => true,
        ]);
        SwitchMedia::factory()->for($account)->create([
            'name' => 'Private document',
            'content_type' => 'application/pdf',
            'streamable' => true,
        ]);
        SwitchMedia::factory()->for($account)->create([
            'name' => 'Non-streamable audio',
            'content_type' => 'audio/wav',
            'streamable' => false,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/conferences/options")
            ->assertOk()
            ->assertJsonCount(3, 'data.media')
            ->assertJsonCount(1, 'data.playable_media')
            ->assertJsonPath('data.playable_media.0.id', $audio->id)
            ->assertJsonMissingPath('data.playable_media.0.switch_resource_id');
    }

    public function test_operator_creates_conference_with_owner_numbers_and_write_only_pins(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $owner = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1', 'display_name' => 'Ada Lovelace', 'extension' => '1001']);
        $media = SwitchMedia::factory()->for($account)->create(['switch_resource_id' => 'switch-media-1', 'name' => 'Conference tone']);
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(fn (SwitchAccount $received, array $data): bool => $received->is($account)
            && $data['switch_owner_reference'] === 'switch-user-1' && $data['member_pins'] === ['1234', '5678']
            && $data['moderator_pins'] === ['9876']
            && $data['switch_max_members_media_reference'] === 'switch-media-1'
            && $data['switch_play_entry_tone'] === 'switch-media-1'
            && $data['switch_play_exit_tone'] === false)
            ->andReturn($this->snapshot([
                'owner_id' => 'switch-user-1',
                'member' => ['numbers' => ['7001'], 'pins' => ['1234'], 'join_muted' => true],
                'moderator' => ['numbers' => ['7099'], 'pins' => ['9876']],
                'max_members_media' => 'switch-media-1',
                'play_entry_tone' => 'switch-media-1',
                'play_exit_tone' => false,
            ]));

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/conferences", [
            ...$this->payload(), 'owner_id' => $owner->id,
            'member_pins' => ['1234', '5678'], 'moderator_pins' => ['9876'],
            'max_members_media_id' => $media->id,
            'play_entry_tone_mode' => 'media', 'play_entry_tone_media_id' => $media->id,
            'play_exit_tone_mode' => 'disabled',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Daily standup')->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonPath('data.member_numbers.0', '7001')->assertJsonPath('data.member_pin_configured', true)
            ->assertJsonPath('data.max_members_media.id', $media->id)
            ->assertJsonPath('data.entry_tone.mode', 'media')->assertJsonPath('data.exit_tone.mode', 'disabled')
            ->assertJsonMissingPath('data.member_pins')->assertJsonMissingPath('data.moderator_pins')
            ->assertJsonMissingPath('data.conference_id')
            ->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_json');
        $conference = SwitchConference::query()->where('id', $response->json('data.id'))->firstOrFail();
        $this->assertSame('[REDACTED]', $conference->switch_json['member']['pins']);
        $this->assertDatabaseHas('switch_conference_numbers', ['switch_conference_id' => $conference->getKey(), 'role' => 'moderator', 'number' => '7099']);
    }

    public function test_read_only_user_cannot_mutate_and_cross_tenant_owner_is_rejected(): void
    {
        $this->mock(SwitchConferenceGateway::class)->shouldNotReceive('create');
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/conferences", $this->payload())->assertForbidden();

        [$operator, $managed] = $this->accessibleAccount();
        $foreignOwner = SwitchExtension::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/conferences", [...$this->payload(), 'owner_id' => $foreignOwner->id])
            ->assertUnprocessable()->assertJsonValidationErrors('owner_id');

        $foreignMedia = SwitchMedia::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/conferences", [...$this->payload(), 'max_members_media_id' => $foreignMedia->id])
            ->assertUnprocessable()->assertJsonValidationErrors('max_members_media_id');
    }

    public function test_pin_lists_cannot_be_replaced_and_removed_together(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchConferenceGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/conferences", [
            ...$this->payload(),
            'member_pins' => ['1234'],
            'clear_member_pin' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('member_pins');
    }

    public function test_operator_update_preserves_unresolved_media_and_custom_tones(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create([
            'switch_resource_id' => 'switch-conference-1',
            'switch_json' => [
                'max_members_media' => 'system-media-full',
                'play_entry_tone' => 'tone_stream://entry',
                'play_exit_tone' => 'tone_stream://exit',
            ],
        ]);
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('update')->once()->withArgs(fn (SwitchAccount $received, string $resourceId, array $data): bool => $received->is($account)
            && $resourceId === 'switch-conference-1'
            && $data['switch_max_members_media_reference'] === 'system-media-full'
            && $data['clear_switch_max_members_media'] === false
            && $data['switch_play_entry_tone'] === 'tone_stream://entry'
            && $data['switch_play_exit_tone'] === 'tone_stream://exit')
            ->andReturn($this->snapshot([
                'max_members_media' => 'system-media-full',
                'play_entry_tone' => 'tone_stream://entry',
                'play_exit_tone' => 'tone_stream://exit',
            ]));

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}", [
            ...$this->payload(),
            'play_entry_tone_mode' => 'current_custom',
            'play_exit_tone_mode' => 'current_custom',
        ])->assertOk()
            ->assertJsonPath('data.entry_tone.mode', 'current_custom')
            ->assertJsonPath('data.exit_tone.mode', 'current_custom');
    }

    public function test_delete_is_blocked_when_a_callflow_references_the_conference(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create(['switch_resource_id' => 'switch-conference-1']);
        SwitchCallflow::factory()->for($account)->create(['switch_json' => ['flow' => ['module' => 'conference', 'data' => ['id' => 'switch-conference-1'], 'children' => []]]]);
        $this->mock(SwitchConferenceGateway::class)->shouldNotReceive('delete');

        $this->actingAs($user)->deleteJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('conference');
    }

    public function test_operator_can_request_a_lock_for_an_active_conference(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create([
            'switch_resource_id' => 'switch-conference-1',
        ]);
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('get')->once()->withArgs(fn (SwitchAccount $received, string $resourceId): bool => $received->is($account)
            && $resourceId === 'switch-conference-1')
            ->andReturn($this->snapshot(['_read_only' => ['members' => 2, 'moderators' => 1, 'duration' => 90, 'is_locked' => false]]));
        $gateway->shouldReceive('setLocked')->once()->withArgs(fn (SwitchAccount $received, string $resourceId, bool $locked): bool => $received->is($account)
            && $resourceId === 'switch-conference-1' && $locked);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/commands", ['action' => 'lock'])
            ->assertAccepted()
            ->assertExactJson(['data' => [
                'accepted' => true,
                'action' => 'lock',
                'message' => 'Switch accepted the conference lock request.',
            ]]);

        $this->assertDatabaseHas('switch_conferences', [
            'conference_id' => $conference->getKey(),
            'active_members' => 2,
            'active_moderators' => 1,
            'is_locked' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conference.lock',
            'outcome' => 'accepted',
            'resource_type' => 'conference',
            'resource_id' => 'switch-conference-1',
        ]);
    }

    public function test_inactive_conference_cannot_be_locked(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create([
            'switch_resource_id' => 'switch-conference-1',
        ]);
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('get')->once()->andReturn($this->snapshot());
        $gateway->shouldNotReceive('setLocked');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/commands", ['action' => 'lock'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conference');

        $this->assertSame('failed', AuditLog::query()->where('action', 'conference.lock')->value('outcome'));
    }

    public function test_operator_can_unlock_a_locked_conference_without_active_participants(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create([
            'switch_resource_id' => 'switch-conference-1',
            'is_locked' => true,
        ]);
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('get')->once()->andReturn($this->snapshot([
            '_read_only' => ['members' => 0, 'moderators' => 0, 'duration' => 0, 'is_locked' => true],
        ]));
        $gateway->shouldReceive('setLocked')->once()->withArgs(fn (SwitchAccount $received, string $resourceId, bool $locked): bool => $received->is($account)
            && $resourceId === 'switch-conference-1' && ! $locked);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/commands", ['action' => 'unlock'])
            ->assertAccepted()
            ->assertJsonPath('data.action', 'unlock');
    }

    public function test_read_only_user_cannot_control_a_conference(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $conference = SwitchConference::factory()->for($account)->create();
        $this->mock(SwitchConferenceGateway::class)->shouldNotReceive('get');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/commands", ['action' => 'lock'])
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'name' => 'Daily standup', 'owner_id' => null, 'conference_numbers' => ['7000'],
            'member_numbers' => ['7001'], 'moderator_numbers' => ['7099'], 'member_pins' => [],
            'clear_member_pin' => false, 'moderator_pins' => [], 'clear_moderator_pin' => false,
            'member_join_muted' => true, 'member_join_deaf' => false, 'member_play_entry_prompt' => false,
            'moderator_join_muted' => false, 'moderator_join_deaf' => false, 'max_participants' => 50,
            'language' => 'en-US', 'profile_name' => null, 'caller_controls' => null, 'moderator_controls' => null,
            'play_name' => false, 'play_welcome' => true, 'require_moderator' => true, 'wait_for_moderator' => true,
            'max_members_media_id' => null, 'play_entry_tone_mode' => 'enabled', 'play_entry_tone_media_id' => null,
            'play_exit_tone_mode' => 'enabled', 'play_exit_tone_media_id' => null,
        ];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function snapshot(array $overrides = []): array
    {
        return [
            'id' => 'switch-conference-1', 'name' => 'Daily standup', 'conference_numbers' => ['7000'],
            'member' => ['numbers' => ['7001']], 'moderator' => ['numbers' => ['7099']],
            'max_participants' => 50, 'require_moderator' => true, 'wait_for_moderator' => true,
            '_read_only' => ['members' => 0, 'moderators' => 0, 'duration' => 0, 'is_locked' => false], ...$overrides,
        ];
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
