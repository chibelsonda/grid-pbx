<?php

namespace Tests\Feature\Domains\Queues;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use App\Domains\Queues\Models\SwitchQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QueueControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_queue_with_resolved_agents_and_music_on_hold(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $agent = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $media = SwitchMedia::factory()->for($account)->create(['switch_resource_id' => 'switch-media-1']);
        $gateway = $this->mock(SwitchQueueGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(function (SwitchAccount $received, array $data) use ($account): bool {
            return $received->is($account)
                && $data['resolved_agent_ids'] === ['switch-user-1']
                && $data['switch_music_on_hold_reference'] === 'switch-media-1'
                && $data['switch_announce_media_reference'] === 'switch-media-1'
                && $data['switch_max_priority'] === 10
                && $data['switch_announcements']['media']['you_are_at_position'] === 'switch-media-1';
        })->andReturn($this->snapshot([
            'announce' => 'switch-media-1',
            'max_priority' => 10,
            'announcements' => [
                'interval' => 30,
                'position_announcements_enabled' => true,
                'wait_time_announcements_enabled' => false,
                'media' => [
                    'in_the_queue' => 'switch-media-1',
                    'increase_in_call_volume' => 'switch-media-1',
                    'the_estimated_wait_time_is' => 'switch-media-1',
                    'you_are_at_position' => 'switch-media-1',
                ],
            ],
        ]));
        $gateway->shouldReceive('replaceRoster')->once()->withArgs(fn (SwitchAccount $received, string $queueId, array $agentIds): bool => $received->is($account) && $queueId === 'switch-queue-1' && $agentIds === ['switch-user-1'])
            ->andReturn($this->snapshot([
                'agents' => ['switch-user-1'],
                'announce' => 'switch-media-1',
                'max_priority' => 10,
                'announcements' => [
                    'interval' => 30,
                    'position_announcements_enabled' => true,
                    'wait_time_announcements_enabled' => false,
                    'media' => [
                        'in_the_queue' => 'switch-media-1',
                        'increase_in_call_volume' => 'switch-media-1',
                        'the_estimated_wait_time_is' => 'switch-media-1',
                        'you_are_at_position' => 'switch-media-1',
                    ],
                ],
            ]));

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/queues", [
            ...$this->payload(),
            'music_on_hold_media_id' => $media->id,
            'announce_media_id' => $media->id,
            'announcement_in_the_queue_media_id' => $media->id,
            'announcement_increase_in_call_volume_media_id' => $media->id,
            'announcement_estimated_wait_time_media_id' => $media->id,
            'announcement_position_media_id' => $media->id,
            'agent_ids' => [$agent->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Support')
            ->assertJsonPath('data.agents.0.agent.id', $agent->id)
            ->assertJsonPath('data.music_on_hold_media.id', $media->id)
            ->assertJsonPath('data.announce_media.id', $media->id)
            ->assertJsonPath('data.max_priority', 10)
            ->assertJsonPath('data.announcements.media.you_are_at_position.id', $media->id)
            ->assertJsonMissingPath('data.queue_id')->assertJsonMissingPath('data.switch_json');
        $this->assertDatabaseHas('switch_queues', ['id' => $response->json('data.id'), 'switch_resource_id' => 'switch-queue-1']);
        $this->assertDatabaseHas('switch_queue_agents', ['switch_user_resource_id' => 'switch-user-1']);
    }

    public function test_read_only_user_cannot_mutate_and_cross_tenant_agent_is_rejected(): void
    {
        $this->mock(SwitchQueueGateway::class)->shouldNotReceive('create');
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/queues", $this->payload())->assertForbidden();

        [$operator, $managed] = $this->accessibleAccount();
        $foreignAgent = SwitchExtension::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/queues", [
            ...$this->payload(), 'agent_ids' => [$foreignAgent->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('agent_ids');
    }

    public function test_accessible_user_lists_only_account_queues(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $queue = SwitchQueue::factory()->for($account)->create(['name' => 'Support']);
        SwitchQueue::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/queues")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $queue->id)
            ->assertJsonMissingPath('data.0.switch_resource_id');
    }

    public function test_update_rejects_create_only_priority_and_partial_custom_announcement_media(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $queue = SwitchQueue::factory()->for($account)->create();

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}/queues/{$queue->id}", [
            ...$this->payload(),
            'max_priority' => 20,
            'announcement_in_the_queue_media_id' => fake()->uuid(),
            'cdr_url' => 'https://cdr.example.test/events',
            'recording_url' => 'https://recordings.example.test/audio',
        ])->assertUnprocessable()->assertJsonValidationErrors(['max_priority', 'announcement_media', 'cdr_url', 'recording_url']);
    }

    public function test_update_preserves_hidden_delivery_urls_and_create_only_priority(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $queue = SwitchQueue::factory()->for($account)->create([
            'switch_resource_id' => 'switch-queue-1',
            'switch_json' => [
                'max_priority' => 25,
                'cdr_url' => 'https://cdr.example.test/events',
                'recording_url' => 'https://recordings.example.test/audio',
            ],
        ]);
        $payload = $this->payload();
        unset($payload['max_priority']);
        $snapshot = $this->snapshot([
            'max_priority' => 25,
            'cdr_url' => 'https://cdr.example.test/events',
            'recording_url' => 'https://recordings.example.test/audio',
            'agents' => [],
        ]);
        $gateway = $this->mock(SwitchQueueGateway::class);
        $gateway->shouldReceive('update')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $data): bool => $received->is($account)
                && $resourceId === 'switch-queue-1'
                && $data['switch_max_priority'] === 25
                && $data['switch_cdr_url'] === 'https://cdr.example.test/events'
                && $data['switch_recording_url'] === 'https://recordings.example.test/audio',
        )->andReturn($snapshot);
        $gateway->shouldReceive('replaceRoster')->once()->andReturn($snapshot);

        $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}/queues/{$queue->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.max_priority', 25)
            ->assertJsonMissingPath('data.cdr_url')
            ->assertJsonMissingPath('data.recording_url');
    }

    public function test_operator_requests_live_status_with_a_public_agent_id(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $agent = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $queue = SwitchQueue::factory()->for($account)->create();
        $queue->agents()->create(['switch_extension_id' => $agent->getKey(), 'switch_user_resource_id' => 'switch-user-1']);
        $this->mock(SwitchAgentGateway::class)->shouldReceive('status')->once()->withArgs(fn (SwitchAccount $received, string $switchUserId): bool => $received->is($account) && $switchUserId === 'switch-user-1')
            ->andReturn(['agent_id' => 'switch-user-1', 'status' => 'logged_in', 'timestamp' => 63800000000]);

        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/agents/{$agent->id}/status")
            ->assertOk()->assertJsonPath('data.id', $agent->id)->assertJsonPath('data.status', 'logged_in')
            ->assertJsonMissingPath('data.agent_id');
    }

    public function test_operator_requests_an_audited_agent_status_change(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $agent = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $queue = SwitchQueue::factory()->for($account)->create();
        $queue->agents()->create(['switch_extension_id' => $agent->getKey(), 'switch_user_resource_id' => 'switch-user-1']);
        $this->mock(SwitchAgentGateway::class)->shouldReceive('updateStatus')->once()
            ->withArgs(fn (SwitchAccount $received, string $switchUserId, string $status, ?int $timeout): bool => $received->is($account) && $switchUserId === 'switch-user-1' && $status === 'pause' && $timeout === 60);

        $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/agents/{$agent->id}/status", [
            'status' => 'pause', 'pause_timeout' => 60,
        ])->assertAccepted()->assertJsonPath('data.id', $agent->id)->assertJsonMissing(['switch-user-1']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent.status_requested', 'outcome' => 'succeeded', 'resource_type' => 'agent']);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'name' => 'Support', 'strategy' => 'round_robin',
            'agent_ring_timeout' => 15, 'agent_wrapup_time' => 5,
            'connection_timeout' => 3600, 'max_queue_size' => 20,
            'ring_simultaneously' => 1, 'enter_when_empty' => true,
            'record_caller' => false, 'caller_exit_key' => '#',
            'music_on_hold_media_id' => null, 'announce_media_id' => null,
            'max_priority' => 10, 'announcements_enabled' => true, 'announcement_interval' => 30,
            'position_announcements_enabled' => true, 'wait_time_announcements_enabled' => false,
            'announcement_in_the_queue_media_id' => null,
            'announcement_increase_in_call_volume_media_id' => null,
            'announcement_estimated_wait_time_media_id' => null,
            'announcement_position_media_id' => null, 'agent_ids' => [],
        ];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function snapshot(array $overrides): array
    {
        return [...$this->payload(), 'id' => 'switch-queue-1', 'moh' => 'switch-media-1', ...$overrides];
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
