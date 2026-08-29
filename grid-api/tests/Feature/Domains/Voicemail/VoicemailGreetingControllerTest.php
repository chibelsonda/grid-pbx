<?php

namespace Tests\Feature\Domains\Voicemail;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailGreetingGateway;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

class VoicemailGreetingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_can_upload_and_assign_an_unavailable_greeting(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-vmbox-1',
            'mailbox' => '1001',
        ]);
        $gateway = $this->mock(SwitchVoicemailGreetingGateway::class);
        $gateway->shouldReceive('create')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $voicemailBoxResourceId,
                string $name,
                string $description,
            ): bool => $receivedAccount->is($account)
                && $voicemailBoxResourceId === 'switch-vmbox-1'
                && $name === 'Reception greeting'
                && $description === 'greeting.mp3')
            ->andReturn(['id' => 'switch-media-1', 'name' => 'Reception greeting']);
        $gateway->shouldReceive('upload')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $mediaResourceId,
                StreamInterface $stream,
                string $contentType,
                int $contentLength,
            ): bool => $receivedAccount->is($account)
                && $mediaResourceId === 'switch-media-1'
                && $stream->isReadable()
                && $contentType === 'audio/mpeg'
                && $contentLength > 0)
            ->andReturn([
                'id' => 'switch-media-1',
                'name' => 'Reception greeting',
                'description' => 'greeting.mp3',
                'content_type' => 'audio/mpeg',
                'content_length' => 4096,
                'media_source' => 'upload',
                'streamable' => true,
            ]);
        $gateway->shouldReceive('assign')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $voicemailBoxResourceId,
                ?string $mediaResourceId,
            ): bool => $receivedAccount->is($account)
                && $voicemailBoxResourceId === 'switch-vmbox-1'
                && $mediaResourceId === 'switch-media-1')
            ->andReturn([
                'id' => 'switch-vmbox-1',
                'mailbox' => '1001',
                'media' => ['unavailable' => 'switch-media-1'],
            ]);

        $this->actingAs($user)
            ->post(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/greeting",
                [
                    'name' => 'Reception greeting',
                    'audio' => UploadedFile::fake()->create('greeting.mp3', 4, 'audio/mpeg'),
                ],
                ['Accept' => 'application/json'],
            )
            ->assertCreated()
            ->assertJsonPath('data.name', 'Reception greeting')
            ->assertJsonPath('data.content_type', 'audio/mpeg')
            ->assertJsonMissingPath('data.switch_json');

        $this->assertDatabaseHas('switch_voicemail_greetings', [
            'switch_voicemail_box_id' => $voicemailBox->getKey(),
            'switch_resource_id' => 'switch-media-1',
            'type' => 'unavailable',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voicemail_greeting.uploaded',
            'outcome' => 'succeeded',
            'resource_id' => 'switch-media-1',
        ]);
    }

    public function test_accessible_user_can_stream_greeting_audio_with_a_range(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $greeting = SwitchVoicemailGreeting::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create(['switch_resource_id' => 'switch-media-1']);
        $this->mock(SwitchVoicemailGreetingGateway::class)
            ->shouldReceive('audio')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $mediaResourceId,
                ?string $range,
            ): bool => $receivedAccount->is($account)
                && $mediaResourceId === 'switch-media-1'
                && $range === 'bytes=0-3')
            ->andReturn(new BinaryResponse(
                Utils::streamFor('MP3!'),
                206,
                'audio/mpeg',
                4,
                'bytes 0-3/10',
            ));

        $response = $this->actingAs($user)
            ->withHeader('Range', 'bytes=0-3')
            ->get(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/greeting/audio",
            );

        $response
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('Content-Range', 'bytes 0-3/10')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('MP3!', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voicemail_greeting.streamed',
            'resource_id' => $greeting->switch_resource_id,
        ]);
    }

    public function test_manager_can_detach_a_greeting_without_deleting_shared_switch_media(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-vmbox-1',
        ]);
        $greeting = SwitchVoicemailGreeting::factory()
            ->for($account, 'switchAccount')
            ->for($voicemailBox, 'voicemailBox')
            ->create(['switch_resource_id' => 'switch-media-1']);
        $gateway = $this->mock(SwitchVoicemailGreetingGateway::class);
        $gateway->shouldReceive('assign')
            ->once()
            ->withArgs(fn (
                SwitchAccount $receivedAccount,
                string $voicemailBoxResourceId,
                ?string $mediaResourceId,
            ): bool => $receivedAccount->is($account)
                && $voicemailBoxResourceId === 'switch-vmbox-1'
                && $mediaResourceId === null)
            ->andReturn(['id' => 'switch-vmbox-1', 'media' => []]);
        $gateway->shouldNotReceive('delete');

        $this->actingAs($user)
            ->deleteJson(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/greeting",
            )
            ->assertNoContent();

        $this->assertSoftDeleted('switch_voicemail_greetings', ['id' => $greeting->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voicemail_greeting.detached',
            'resource_id' => 'switch-media-1',
        ]);
    }

    public function test_read_only_user_cannot_upload_a_greeting(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $voicemailBox = SwitchVoicemailBox::factory()->for($account)->create();
        $this->mock(SwitchVoicemailGreetingGateway::class)->shouldNotReceive('create');

        $this->actingAs($user)
            ->post(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$voicemailBox->id}/greeting",
                ['audio' => UploadedFile::fake()->create('greeting.mp3', 4, 'audio/mpeg')],
                ['Accept' => 'application/json'],
            )
            ->assertForbidden();
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
