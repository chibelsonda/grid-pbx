<?php

namespace Tests\Feature\Domains\Media;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accessible_user_views_the_selected_music_on_hold_without_switch_identifiers(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $media = SwitchMedia::factory()->for($account)->create([
            'name' => 'Main hold music',
            'switch_resource_id' => 'private-media-id',
            'switch_json' => ['private' => 'server-only'],
        ]);
        $account->update(['music_on_hold_media_id' => $media->getKey()]);
        $this->mock(SwitchMediaGateway::class)->shouldIgnoreMissing();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/media/music-on-hold")
            ->assertOk()
            ->assertJsonPath('data.media.id', $media->id)
            ->assertJsonPath('data.media.name', 'Main hold music')
            ->assertJsonMissing(['private-media-id', 'server-only'])
            ->assertJsonMissingPath('data.media.switch_resource_id')
            ->assertJsonMissingPath('data.media.switch_json');
    }

    public function test_accessible_user_lists_and_views_safe_media_with_dependencies(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $media = SwitchMedia::factory()->for($account)->create([
            'switch_resource_id' => 'switch-media-hold',
            'name' => 'Main hold music',
            'switch_json' => ['id' => 'switch-media-hold', 'private' => 'server-only'],
        ]);
        $account->update(['music_on_hold_media_id' => $media->getKey()]);
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        SwitchVoicemailGreeting::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create(['switch_resource_id' => 'switch-media-hold']);
        SwitchCallflow::factory()->for($account)->create([
            'modules' => ['play'],
            'switch_json' => [
                'flow' => [
                    'module' => 'play',
                    'data' => ['id' => 'switch-media-hold'],
                    'children' => [],
                ],
            ],
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'modules' => ['ring_group'],
            'switch_json' => [
                'flow' => [
                    'module' => 'ring_group',
                    'data' => ['ringback' => 'switch-media-hold'],
                    'children' => [],
                ],
            ],
        ]);
        $otherMedia = SwitchMedia::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/media?search=hold")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $media->id)
            ->assertJsonPath('data.0.is_music_on_hold', true)
            ->assertJsonPath('meta.sync.status', 'stale')
            ->assertJsonMissingPath('data.0.media_id')
            ->assertJsonMissingPath('data.0.switch_resource_id')
            ->assertJsonMissingPath('data.0.switch_json');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/media/{$media->id}")
            ->assertOk()
            ->assertJsonPath('data.dependencies.music_on_hold', 1)
            ->assertJsonPath('data.dependencies.voicemail_greetings', 1)
            ->assertJsonPath('data.dependencies.callflows', 2)
            ->assertJsonPath('data.dependencies.can_delete', false)
            ->assertJsonMissing(['server-only']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/media/{$otherMedia->id}")
            ->assertNotFound();
    }

    public function test_manager_creates_media_metadata_and_uploads_audio(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchMediaGateway::class);
        $gateway->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $received, array $data): bool => $received->is($account)
                && $data['name'] === 'Lobby music'
                && $data['language'] === 'en-us')
            ->andReturn(['id' => 'switch-media-1', 'name' => 'Lobby music']);
        $gateway->shouldReceive('upload')
            ->once()
            ->withArgs(fn (
                SwitchAccount $received,
                string $resourceId,
                StreamInterface $stream,
                string $contentType,
                int $contentLength,
            ): bool => $received->is($account)
                && $resourceId === 'switch-media-1'
                && $stream->isReadable()
                && $contentType === 'audio/mpeg'
                && $contentLength > 0)
            ->andReturn([
                'id' => 'switch-media-1',
                'name' => 'Lobby music',
                'description' => 'Welcome loop',
                'language' => 'en-us',
                'media_source' => 'upload',
                'content_type' => 'audio/mpeg',
                'content_length' => 4096,
                'streamable' => true,
            ]);

        $response = $this->actingAs($user)->post(
            "/api/v1/accounts/{$account->id}/media",
            [
                'name' => 'Lobby music',
                'description' => 'Welcome loop',
                'language' => 'en-us',
                'streamable' => true,
                'audio' => UploadedFile::fake()->create('lobby.mp3', 4, 'audio/mpeg'),
            ],
            ['Accept' => 'application/json'],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Lobby music')
            ->assertJsonPath('data.content_type', 'audio/mpeg')
            ->assertJsonMissingPath('data.switch_resource_id');
        $this->assertDatabaseHas('switch_media', [
            'id' => $response->json('data.id'),
            'switch_resource_id' => 'switch-media-1',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.created', 'outcome' => 'succeeded']);
    }

    public function test_manager_updates_metadata_replaces_audio_and_assigns_music_on_hold(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $media = SwitchMedia::factory()->for($account)->create([
            'switch_resource_id' => 'switch-media-1',
            'name' => 'Old name',
            'switch_json' => [
                'id' => 'switch-media-1',
                'name' => 'Old name',
                'media_source' => 'tts',
                'content_type' => 'audio/mpeg',
                'content_length' => 4096,
                'prompt_id' => 'system_prompt',
                'source_id' => '0123456789abcdef0123456789abcdef',
                'source_type' => 'callflow',
                'tts' => [
                    'text' => 'Welcome.',
                    'voice' => 'female/en-US',
                    'provider_option' => 'preserved',
                    'provider_secret' => '[REDACTED]',
                ],
                'custom_metadata' => [
                    'managed_by' => 'external-app',
                    'nullable_option' => null,
                ],
                'redacted_value' => '[REDACTED]',
                '_read_only' => ['private' => 'not writable'],
            ],
            'media_source' => 'tts',
        ]);
        $gateway = $this->mock(SwitchMediaGateway::class);
        $gateway->shouldReceive('update')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $data): bool => $received->is($account)
                && $resourceId === 'switch-media-1'
                && $data['media_source'] === 'tts'
                && $data['content_type'] === 'audio/mpeg'
                && $data['content_length'] === 4096
                && $data['prompt_id'] === 'system_prompt'
                && $data['source_id'] === '0123456789abcdef0123456789abcdef'
                && $data['source_type'] === 'callflow'
                && $data['tts_text'] === 'Welcome.'
                && $data['tts_voice'] === 'female/en-US'
                && $data['tts_preserved_options'] === ['provider_option' => 'preserved']
                && $data['preserved_options'] === [
                    'custom_metadata' => [
                        'managed_by' => 'external-app',
                        'nullable_option' => null,
                    ],
                ],
        )->andReturn([
            'id' => 'switch-media-1',
            'name' => 'Main hold music',
            'description' => 'New loop',
            'language' => 'en-gb',
            'media_source' => 'upload',
            'content_type' => 'audio/mpeg',
            'content_length' => 2048,
            'streamable' => true,
        ]);
        $gateway->shouldReceive('upload')->once()->andReturn([
            'id' => 'switch-media-1',
            'name' => 'Main hold music',
            'description' => 'New loop',
            'language' => 'en-gb',
            'media_source' => 'upload',
            'content_type' => 'audio/ogg',
            'content_length' => 1024,
            'streamable' => true,
        ]);
        $gateway->shouldReceive('updateAccountMusicOnHold')
            ->once()
            ->withArgs(fn (SwitchAccount $received, ?string $resourceId): bool => $received->is($account)
                && $resourceId === 'switch-media-1')
            ->andReturn('switch-media-1');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/media/{$media->id}", [
                'name' => 'Main hold music',
                'description' => 'New loop',
                'language' => 'en-gb',
                'streamable' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Main hold music');

        $this->actingAs($user)->post(
            "/api/v1/accounts/{$account->id}/media/{$media->id}/audio",
            ['audio' => UploadedFile::fake()->create('replacement.ogg', 1, 'audio/ogg')],
            ['Accept' => 'application/json'],
        )->assertOk()->assertJsonPath('data.content_type', 'audio/ogg');

        $this->actingAs($user)
            ->putJson("/api/v1/accounts/{$account->id}/media/music-on-hold", ['media_id' => $media->id])
            ->assertOk()
            ->assertJsonPath('data.media.id', $media->id);

        $this->assertSame($media->getKey(), $account->fresh()->music_on_hold_media_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'music_on_hold.updated']);
    }

    public function test_accessible_user_streams_audio_with_a_valid_range(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $media = SwitchMedia::factory()->for($account)->create(['switch_resource_id' => 'switch-media-1']);
        $this->mock(SwitchMediaGateway::class)
            ->shouldReceive('audio')
            ->once()
            ->andReturn(new BinaryResponse(Utils::streamFor('MP3!'), 206, 'audio/mpeg', 4, 'bytes 0-3/10'));

        $response = $this->actingAs($user)
            ->withHeader('Range', 'bytes=0-3')
            ->get("/api/v1/accounts/{$account->id}/media/{$media->id}/audio");

        $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-3/10');
        $this->assertSame('MP3!', $response->streamedContent());

        $this->actingAs($user)
            ->withHeader('Range', 'items=0-3')
            ->get("/api/v1/accounts/{$account->id}/media/{$media->id}/audio")
            ->assertStatus(416);
    }

    public function test_delete_is_blocked_while_media_is_assigned_and_succeeds_when_unused(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $assigned = SwitchMedia::factory()->for($account)->create();
        $unused = SwitchMedia::factory()->for($account)->create(['switch_resource_id' => 'switch-media-unused']);
        $account->update(['music_on_hold_media_id' => $assigned->getKey()]);
        $gateway = $this->mock(SwitchMediaGateway::class);
        $gateway->shouldReceive('delete')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId): bool => $received->is($account)
                && $resourceId === 'switch-media-unused',
        );

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/media/{$assigned->id}")
            ->assertConflict();
        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/media/{$unused->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($unused);
    }

    public function test_read_only_user_cannot_mutate_media_and_invalid_upload_is_rejected(): void
    {
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->mock(SwitchMediaGateway::class)->shouldNotReceive('create');

        $this->actingAs($readOnly)->post(
            "/api/v1/accounts/{$account->id}/media",
            [
                'name' => 'Unsafe',
                'audio' => UploadedFile::fake()->create('valid.mp3', 1, 'audio/mpeg'),
            ],
            ['Accept' => 'application/json'],
        )->assertForbidden();

        [$manager, $managedAccount] = $this->accessibleAccount();
        $this->actingAs($manager)->post(
            "/api/v1/accounts/{$managedAccount->id}/media",
            [
                'name' => 'Unsafe',
                'audio' => UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml'),
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors('audio');

        $this->actingAs($manager)
            ->postJson("/api/v1/accounts/{$managedAccount->id}/media", [
                'name' => 'Unsafe metadata',
                'media_source' => 'tts',
                'tts' => ['text' => 'Injected', 'voice' => 'female/en-US'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media_source', 'tts']);
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
