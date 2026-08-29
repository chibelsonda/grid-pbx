<?php

namespace Tests\Feature\Domains\Voicemail;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailMessageGateway;
use App\Domains\Voicemail\Enums\VoicemailMessageFolder;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VoicemailMessageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_searches_and_filters_projected_message_metadata(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create([
                'folder' => 'new',
                'caller_id_name' => 'Alice Customer',
                'caller_id_number' => '+15551234567',
                'transcription_text' => 'Please call me back.',
                'switch_json' => ['private' => 'not-returned'],
            ]);
        SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create(['folder' => 'saved', 'caller_id_name' => 'Bob Customer']);

        $this->actingAs($user)
            ->getJson(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages?folder=new&search=Alice",
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.folder', 'new')
            ->assertJsonPath('data.0.caller_id_name', 'Alice Customer')
            ->assertJsonPath('data.0.transcription_text', 'Please call me back.')
            ->assertJsonMissingPath('data.0.switch_json')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_audio_stream_forwards_range_and_returns_only_safe_headers(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-vmbox-1',
        ]);
        $message = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create(['switch_resource_id' => 'switch-message-1']);
        $this->mock(SwitchVoicemailMessageGateway::class)
            ->shouldReceive('audio')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $voicemailBoxResourceId,
                string $messageResourceId,
                ?string $range,
            ): bool => $receivedAccount->is($account)
                && $voicemailBoxResourceId === 'switch-vmbox-1'
                && $messageResourceId === 'switch-message-1'
                && $range === 'bytes=0-3')
            ->andReturn(new BinaryResponse(
                Utils::streamFor('MP3!'),
                206,
                'audio/mpeg',
                4,
                'bytes 0-3/10',
            ));

        $response = $this->actingAs($user)->withHeader('Range', 'bytes=0-3')->get(
            "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages/{$message->id}/audio",
        );

        $response
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('Content-Length', '4')
            ->assertHeader('Content-Range', 'bytes 0-3/10')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('MP3!', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voicemail_message.streamed',
            'resource_type' => 'voicemail_message',
            'resource_id' => 'switch-message-1',
        ]);
    }

    public function test_it_rejects_multi_range_requests_before_calling_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $message = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create();
        $this->mock(SwitchVoicemailMessageGateway::class)->shouldNotReceive('audio');

        $this->actingAs($user)
            ->withHeader('Range', 'bytes=0-3,6-9')
            ->get(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages/{$message->id}/audio",
            )
            ->assertStatus(416);
    }

    public function test_message_from_another_mailbox_is_not_accessible(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $otherVoicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $message = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($otherVoicemailBox, 'voicemailBox')
            ->create();
        $this->mock(SwitchVoicemailMessageGateway::class)->shouldNotReceive('audio');

        $this->actingAs($user)
            ->get(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages/{$message->id}/audio",
            )
            ->assertNotFound();
    }

    public function test_manager_can_change_a_message_folder_and_refresh_its_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-vmbox-1',
        ]);
        $message = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create([
                'switch_resource_id' => 'switch-message-1',
                'folder' => 'new',
                'caller_id_name' => 'Old caller',
            ]);
        $this->mock(SwitchVoicemailMessageGateway::class)
            ->shouldReceive('changeFolder')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $voicemailBoxResourceId,
                string $messageResourceId,
                VoicemailMessageFolder $folder,
            ): bool => $receivedAccount->is($account)
                && $voicemailBoxResourceId === 'switch-vmbox-1'
                && $messageResourceId === 'switch-message-1'
                && $folder === VoicemailMessageFolder::Saved)
            ->andReturn([
                'media_id' => 'switch-message-1',
                'folder' => 'saved',
                'caller_id_name' => 'Updated caller',
                'caller_id_number' => '+15551234567',
                'length' => 42000,
                'timestamp' => 63891972000,
                'transcription' => ['result' => 'success', 'text' => 'Call me back.'],
            ]);

        $this->actingAs($user)
            ->patchJson(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages/{$message->id}",
                ['folder' => 'saved'],
            )
            ->assertOk()
            ->assertJsonPath('data.id', $message->id)
            ->assertJsonPath('data.folder', 'saved')
            ->assertJsonPath('data.caller_id_name', 'Updated caller')
            ->assertJsonPath('data.transcription_text', 'Call me back.')
            ->assertJsonMissingPath('data.switch_json');

        $this->assertDatabaseHas('switch_voicemail_messages', [
            'id' => $message->id,
            'folder' => 'saved',
            'caller_id_name' => 'Updated caller',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voicemail_message.folder_changed',
            'outcome' => 'succeeded',
            'resource_id' => 'switch-message-1',
        ]);
    }

    public function test_read_only_user_cannot_change_a_message_folder(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $message = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create();
        $this->mock(SwitchVoicemailMessageGateway::class)->shouldNotReceive('changeFolder');

        $this->actingAs($user)
            ->patchJson(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages/{$message->id}",
                ['folder' => 'saved'],
            )
            ->assertForbidden();
    }

    public function test_manager_can_bulk_change_folders_with_partial_failure_reporting(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-vmbox-1',
        ]);
        $first = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create(['switch_resource_id' => 'switch-message-1', 'folder' => 'new']);
        $second = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create(['switch_resource_id' => 'switch-message-2', 'folder' => 'new']);
        $this->mock(SwitchVoicemailMessageGateway::class)
            ->shouldReceive('changeFolders')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $voicemailBoxResourceId,
                array $messageResourceIds,
                VoicemailMessageFolder $folder,
            ): bool => $receivedAccount->is($account)
                && $voicemailBoxResourceId === 'switch-vmbox-1'
                && collect($messageResourceIds)->sort()->values()->all() === ['switch-message-1', 'switch-message-2']
                && $folder === VoicemailMessageFolder::Deleted)
            ->andReturn([
                'succeeded' => ['switch-message-1'],
                'failed' => ['switch-message-2' => 'not_found'],
            ]);

        $this->actingAs($user)
            ->patchJson(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages",
                ['message_ids' => [$first->id, $second->id], 'folder' => 'deleted'],
            )
            ->assertOk()
            ->assertJsonPath('data.folder', 'deleted')
            ->assertJsonPath('data.succeeded.0', $first->id)
            ->assertJsonPath('data.failed.0.id', $second->id)
            ->assertJsonPath('data.failed.0.reason', 'not_found');

        $this->assertDatabaseHas('switch_voicemail_messages', [
            'id' => $first->id,
            'folder' => 'deleted',
        ]);
        $this->assertDatabaseHas('switch_voicemail_messages', [
            'id' => $second->id,
            'folder' => 'new',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voicemail_message.bulk_folder_changed',
            'outcome' => 'partial',
        ]);
    }

    public function test_bulk_change_rejects_a_message_from_another_mailbox_before_calling_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $otherVoicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $message = SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($otherVoicemailBox, 'voicemailBox')
            ->create();
        $this->mock(SwitchVoicemailMessageGateway::class)->shouldNotReceive('changeFolders');

        $this->actingAs($user)
            ->patchJson(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages",
                ['message_ids' => [$message->id], 'folder' => 'saved'],
            )
            ->assertNotFound();
    }

    public function test_bulk_change_validates_local_message_ids_and_folder_before_calling_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $this->mock(SwitchVoicemailMessageGateway::class)->shouldNotReceive('changeFolders');

        $this->actingAs($user)
            ->patchJson(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/messages",
                ['message_ids' => ['not-a-local-ulid'], 'folder' => 'archived'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message_ids.0', 'folder']);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(
        OrganizationRole $role = OrganizationRole::AccountOperator,
    ): array {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
