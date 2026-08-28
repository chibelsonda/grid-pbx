<?php

namespace Tests\Feature\Domains\Voicemail;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailBoxGateway;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use GridPbx\Switch\Exceptions\SwitchRequestException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VoicemailBoxControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_searches_and_returns_mailbox_details_without_switch_json(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        $mailbox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'name' => 'Alice voicemail',
            'mailbox' => '1001',
            'timezone' => 'Asia/Manila',
            'notification_emails' => ['alice@example.com'],
            'transcribe' => true,
            'switch_json' => ['pin' => '[REDACTED]'],
        ]);
        SwitchVoicemailBox::factory()->for($account)->create(['mailbox' => '2001']);
        SwitchVoicemailMessage::factory()
            ->for($account, 'switchAccount')
            ->for($mailbox, 'voicemailBox')
            ->create(['folder' => 'new']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/voicemail-boxes?search=Alice")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mailbox', '1001')
            ->assertJsonPath('data.0.assigned_extension.id', $extension->id);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/voicemail-boxes/{$mailbox->id}")
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.notification_emails.0', 'alice@example.com')
            ->assertJsonPath('data.transcribe', true)
            ->assertJsonPath('data.message_counts.total', 1)
            ->assertJsonPath('data.message_counts.new', 1)
            ->assertJsonMissingPath('data.switch_json')
            ->assertDontSee('[REDACTED]');
    }

    public function test_it_creates_an_upstream_mailbox_and_stores_a_redacted_projection_and_audit(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
        ]);
        $this->mock(SwitchVoicemailBoxGateway::class)
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (SwitchAccount $received, array $payload): bool => $received->is($account)
                && $payload['owner_switch_resource_id'] === 'switch-user-1'
                && $payload['pin'] === '123456')
            ->andReturn([
                'id' => 'switch-vmbox-1',
                'owner_id' => 'switch-user-1',
                'name' => 'Alice voicemail',
                'mailbox' => '1001',
                'timezone' => 'Asia/Manila',
                'notify_email_addresses' => ['alice@example.com'],
                'transcribe' => true,
                'require_pin' => true,
                'pin' => '123456',
                'is_setup' => false,
            ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/accounts/{$account->id}/voicemail-boxes",
            $this->payload([
                'assigned_extension_id' => $extension->id,
                'pin' => '123456',
            ]),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.mailbox', '1001')
            ->assertJsonPath('data.assigned_extension.id', $extension->id)
            ->assertDontSee('123456');
        $mailbox = SwitchVoicemailBox::query()->where('switch_resource_id', 'switch-vmbox-1')->firstOrFail();
        $this->assertSame('[REDACTED]', $mailbox->switch_json['pin']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voicemail_box.created',
            'resource_type' => 'voicemail_box',
            'outcome' => 'succeeded',
        ]);
        $audit = AuditLog::query()->where('action', 'voicemail_box.created')->firstOrFail();
        $this->assertStringNotContainsString('123456', json_encode($audit->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_it_updates_and_unassigns_a_mailbox_without_overwriting_the_pin(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $mailbox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-vmbox-1',
        ]);
        $this->mock(SwitchVoicemailBoxGateway::class)
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $resourceId, array $payload): bool => $received->is($account)
                && $resourceId === 'switch-vmbox-1'
                && $payload['owner_switch_resource_id'] === null
                && $payload['pin'] === null)
            ->andReturn([
                'id' => 'switch-vmbox-1',
                'name' => 'Shared voicemail',
                'mailbox' => '1001',
                'timezone' => 'UTC',
                'notify_email_addresses' => [],
                'transcribe' => false,
                'require_pin' => false,
            ]);

        $this->actingAs($user)
            ->putJson(
                "/api/v1/accounts/{$account->id}/voicemail-boxes/{$mailbox->id}",
                $this->payload([
                    'name' => 'Shared voicemail',
                    'timezone' => 'UTC',
                    'notification_emails' => [],
                    'transcribe' => false,
                    'require_pin' => false,
                ]),
            )
            ->assertOk()
            ->assertJsonPath('data.name', 'Shared voicemail')
            ->assertJsonPath('data.assigned_extension', null);
    }

    public function test_it_deletes_upstream_then_soft_deletes_the_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $mailbox = SwitchVoicemailBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-vmbox-1',
        ]);
        $this->mock(SwitchVoicemailBoxGateway::class)
            ->shouldReceive('delete')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $resourceId): bool => $received->is($account)
                && $resourceId === 'switch-vmbox-1');

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/voicemail-boxes/{$mailbox->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($mailbox);
        $this->assertDatabaseHas('audit_logs', ['action' => 'voicemail_box.deleted']);
    }

    public function test_validation_and_read_only_access_stop_before_upstream(): void
    {
        [$operator, $account] = $this->accessibleAccount();
        $this->mock(SwitchVoicemailBoxGateway::class)->shouldNotReceive('create');

        $this->actingAs($operator)
            ->postJson("/api/v1/accounts/{$account->id}/voicemail-boxes", $this->payload([
                'mailbox' => 'not-a-number',
                'notification_emails' => ['invalid'],
                'pin' => '12',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mailbox', 'notification_emails.0', 'pin']);

        [$readOnly, $readOnlyAccount] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)
            ->postJson("/api/v1/accounts/{$readOnlyAccount->id}/voicemail-boxes", $this->payload())
            ->assertForbidden();
    }

    public function test_upstream_failure_returns_safe_error_and_failure_audit(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchVoicemailBoxGateway::class)
            ->shouldReceive('create')
            ->once()
            ->andThrow(new SwitchRequestException('pin=123456', 503, ['pin' => '123456']));

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/voicemail-boxes", $this->payload())
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Switch is unavailable. Try again later.'])
            ->assertDontSee('123456');

        $this->assertDatabaseCount('switch_voicemail_boxes', 0);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voicemail_box.create_failed',
            'resource_type' => 'voicemail_box',
            'outcome' => 'failed',
        ]);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Alice voicemail',
            'mailbox' => '1001',
            'assigned_extension_id' => null,
            'timezone' => 'Asia/Manila',
            'notification_emails' => ['alice@example.com'],
            'transcribe' => true,
            'require_pin' => true,
            'pin' => null,
        ], $overrides);
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
