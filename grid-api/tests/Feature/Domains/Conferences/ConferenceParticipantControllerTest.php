<?php

namespace Tests\Feature\Domains\Conferences;

use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConferenceParticipantControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_receives_opaque_participant_handles_and_can_mute_an_active_participant(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create([
            'switch_resource_id' => 'switch-conference-1',
        ]);
        $participant = [
            'id' => '42',
            'display_name' => 'Ada Lovelace',
            'number' => '1001',
            'is_moderator' => false,
            'can_speak' => true,
            'can_hear' => true,
            'duration_seconds' => 37,
        ];
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('participants')->twice()->andReturn([$participant]);
        $gateway->shouldReceive('controlParticipant')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, string $participantId, string $action): bool => $received->is($account)
                && $resourceId === 'switch-conference-1'
                && $participantId === '42'
                && $action === 'mute',
        );

        $list = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants")
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'Ada Lovelace')
            ->assertJsonPath('data.0.number', '1001')
            ->assertJsonMissingPath('data.0.call_id')
            ->assertJsonMissingPath('data.0.switch_hostname');
        $opaqueId = $list->json('data.0.id');

        $this->assertIsString($opaqueId);
        $this->assertNotSame('42', $opaqueId);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants/commands", [
                'participant_id' => $opaqueId,
                'action' => 'mute',
            ])
            ->assertAccepted()
            ->assertExactJson(['data' => [
                'accepted' => true,
                'action' => 'mute',
                'message' => 'Switch accepted the participant mute request.',
            ]]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conference.participant.mute',
            'outcome' => 'accepted',
            'resource_type' => 'conference',
            'resource_id' => 'switch-conference-1',
        ]);
    }

    public function test_tampered_participant_handle_is_rejected_before_switch_control(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create();
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldNotReceive('participants');
        $gateway->shouldNotReceive('controlParticipant');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants/commands", [
                'participant_id' => 'tampered-participant-handle',
                'action' => 'kick',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participant_id');
    }

    public function test_read_only_user_can_view_but_cannot_control_participants(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $conference = SwitchConference::factory()->for($account)->create();
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('participants')->once()->andReturn([]);
        $gateway->shouldNotReceive('controlParticipant');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants")
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants/commands", [
                'participant_id' => 'not-evaluated-for-an-unauthorized-request',
                'action' => 'mute',
            ])
            ->assertForbidden();
    }

    public function test_operator_can_confirm_a_native_room_wide_command_with_a_fresh_preview(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create([
            'switch_resource_id' => 'switch-conference-1',
        ]);
        $participants = [
            [
                'id' => '41',
                'display_name' => 'Member one',
                'number' => '1001',
                'is_moderator' => false,
                'can_speak' => true,
                'can_hear' => true,
                'duration_seconds' => 20,
            ],
            [
                'id' => '42',
                'display_name' => 'Already muted',
                'number' => '1002',
                'is_moderator' => false,
                'can_speak' => false,
                'can_hear' => true,
                'duration_seconds' => 10,
            ],
            [
                'id' => '43',
                'display_name' => 'Moderator',
                'number' => '1003',
                'is_moderator' => true,
                'can_speak' => true,
                'can_hear' => true,
                'duration_seconds' => 30,
            ],
        ];
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('participants')->once()->andReturn($participants);
        $gateway->shouldReceive('controlParticipants')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, string $action): bool => $received->is($account)
                && $resourceId === 'switch-conference-1'
                && $action === 'mute',
        );

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants/bulk-commands", [
                'action' => 'mute',
                'expected_participant_count' => 3,
                'expected_target_count' => 1,
                'confirmation' => true,
            ])
            ->assertAccepted()
            ->assertExactJson(['data' => [
                'accepted' => true,
                'action' => 'mute',
                'targeted_participants' => 1,
                'skipped_moderators' => 1,
                'message' => 'Switch accepted the room-wide mute request for 1 participant(s).',
            ]]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conference.participants.mute',
            'outcome' => 'accepted',
            'resource_type' => 'conference',
            'resource_id' => 'switch-conference-1',
        ]);
    }

    public function test_room_wide_command_rejects_a_stale_preview_before_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create();
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('participants')->once()->andReturn([[
            'id' => '42',
            'is_moderator' => false,
            'can_speak' => true,
            'can_hear' => true,
        ]]);
        $gateway->shouldNotReceive('controlParticipants');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants/bulk-commands", [
                'action' => 'mute',
                'expected_participant_count' => 2,
                'expected_target_count' => 2,
                'confirmation' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participants');
    }

    public function test_room_wide_command_requires_confirmation_and_never_accepts_kick(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create();
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldNotReceive('participants');
        $gateway->shouldNotReceive('controlParticipants');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants/bulk-commands", [
                'action' => 'kick',
                'expected_participant_count' => 1,
                'expected_target_count' => 1,
                'confirmation' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action', 'confirmation']);
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
