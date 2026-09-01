<?php

namespace Tests\Feature\Domains\Conferences;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConferencePlaybackControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_can_play_account_audio_to_an_active_room(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create([
            'switch_resource_id' => 'switch-conference-1',
        ]);
        $media = SwitchMedia::factory()->for($account)->create([
            'switch_resource_id' => 'switch-media-1',
            'content_type' => 'audio/mpeg',
            'streamable' => true,
        ]);
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('participants')->once()->andReturn([$this->participant()]);
        $gateway->shouldReceive('playMedia')->once()->withArgs(
            fn (SwitchAccount $received, string $conferenceId, string $mediaId, ?string $participantId): bool => $received->is($account)
                && $conferenceId === 'switch-conference-1'
                && $mediaId === 'switch-media-1'
                && $participantId === null,
        );

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/playback", [
                'media_id' => $media->id,
                'confirmation' => true,
            ])
            ->assertAccepted()
            ->assertExactJson(['data' => [
                'accepted' => true,
                'action' => 'play_media',
                'target' => 'room',
                'message' => 'Switch accepted the media playback request for the room.',
            ]]);

        $audit = AuditLog::query()->where('action', 'conference.media.play')->firstOrFail();
        $this->assertSame('accepted', $audit->outcome);
        $this->assertSame($media->id, $audit->metadata['media_id']);
        $this->assertArrayNotHasKey('switch_media_id', $audit->metadata);
    }

    public function test_participant_playback_resolves_the_opaque_handle_server_side(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create([
            'switch_resource_id' => 'switch-conference-1',
        ]);
        $media = SwitchMedia::factory()->for($account)->create([
            'switch_resource_id' => 'switch-media-1',
        ]);
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('participants')->twice()->andReturn([$this->participant()]);
        $gateway->shouldReceive('playMedia')->once()->withArgs(
            fn (SwitchAccount $received, string $conferenceId, string $mediaId, ?string $participantId): bool => $received->is($account)
                && $conferenceId === 'switch-conference-1'
                && $mediaId === 'switch-media-1'
                && $participantId === '42',
        );

        $participantHandle = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/participants")
            ->assertOk()
            ->json('data.0.id');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/playback", [
                'media_id' => $media->id,
                'participant_id' => $participantHandle,
                'confirmation' => true,
            ])
            ->assertAccepted()
            ->assertJsonPath('data.target', 'participant')
            ->assertJsonMissingPath('data.participant_id');
    }

    public function test_non_audio_cross_account_and_inactive_room_playback_are_rejected_before_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create();
        $nonAudio = SwitchMedia::factory()->for($account)->create([
            'content_type' => 'application/pdf',
            'streamable' => true,
        ]);
        $foreignMedia = SwitchMedia::factory()->create();
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldNotReceive('playMedia');
        $gateway->shouldReceive('participants')->once()->andReturn([]);

        foreach ([$nonAudio->id, $foreignMedia->id] as $mediaId) {
            $this->actingAs($user)
                ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/playback", [
                    'media_id' => $mediaId,
                    'confirmation' => true,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('media_id');
        }

        $validMedia = SwitchMedia::factory()->for($account)->create();
        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/playback", [
                'media_id' => $validMedia->id,
                'confirmation' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conference');
    }

    public function test_read_only_user_cannot_submit_playback(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $conference = SwitchConference::factory()->for($account)->create();
        $media = SwitchMedia::factory()->for($account)->create();
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldNotReceive('participants');
        $gateway->shouldNotReceive('playMedia');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/playback", [
                'media_id' => $media->id,
                'confirmation' => true,
            ])
            ->assertForbidden();
    }

    public function test_playback_requires_confirmation_and_rejects_raw_urls_before_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create();
        $media = SwitchMedia::factory()->for($account)->create();
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldNotReceive('participants');
        $gateway->shouldNotReceive('playMedia');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/playback", [
                'media_id' => $media->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/playback", [
                'media_id' => $media->id,
                'media_url' => 'https://example.invalid/audio.mp3',
                'confirmation' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('media_url');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}/playback", [
                'media_id' => 'https://example.invalid/audio.mp3',
                'confirmation' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('media_id');
    }

    /** @return array<string, mixed> */
    private function participant(): array
    {
        return [
            'id' => '42',
            'display_name' => 'Ada Lovelace',
            'number' => '1001',
            'is_moderator' => false,
            'can_speak' => true,
            'can_hear' => true,
            'duration_seconds' => 37,
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
