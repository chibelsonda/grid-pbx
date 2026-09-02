<?php

namespace Tests\Feature\Domains\Recordings;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Contracts\SwitchRecordingGateway;
use App\Domains\Recordings\Models\SwitchRecording;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecordingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_user_lists_public_safe_recording_metadata(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $recording = SwitchRecording::factory()->for($account)->create(['name' => 'Support call', 'switch_json' => ['url' => '[REDACTED]', 'secret' => '[REDACTED]']]);

        $response = $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/recordings");

        $response->assertOk()->assertJsonPath('data.0.id', $recording->id)->assertJsonPath('data.0.name', 'Support call')->assertJsonPath('data.0.has_audio', true)->assertJsonMissingPath('data.0.recording_id')->assertJsonMissingPath('data.0.switch_resource_id')->assertJsonMissingPath('data.0.switch_json');
    }

    public function test_authorized_user_streams_audio_with_range_and_auditing_headers(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $recording = SwitchRecording::factory()->for($account)->create();
        $this->mock(SwitchRecordingGateway::class)->shouldReceive('audio')->once()->withArgs(fn (SwitchAccount $received, string $id, ?string $range) => $received->is($account) && $id === $recording->switch_resource_id && $range === 'bytes=0-3')->andReturn(new BinaryResponse(Utils::streamFor('MP3!'), 206, 'audio/mpeg', 4, 'bytes 0-3/10'));

        $response = $this->actingAs($user)->withHeader('Range', 'bytes=0-3')->get("/api/v1/accounts/{$account->id}/recordings/{$recording->id}/audio");

        $response->assertStatus(206)->assertHeader('Content-Type', 'audio/mpeg')->assertHeader('Content-Range', 'bytes 0-3/10');
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('MP3!', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'recording.played', 'resource_type' => 'recording']);
    }

    public function test_recording_audio_rejects_an_invalid_download_option_before_calling_switch(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchRecordingGateway::class)->shouldNotReceive('audio');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/recordings/not-needed/audio?download=sometimes")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('download');
    }

    public function test_cross_account_recording_is_not_exposed(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $foreign = SwitchRecording::factory()->create();
        $this->mock(SwitchRecordingGateway::class)->shouldIgnoreMissing();
        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/recordings/{$foreign->id}")->assertNotFound();
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
