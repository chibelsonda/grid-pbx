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

    public function test_operator_deletes_an_unreferenced_queue_after_clearing_its_roster(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $queue = SwitchQueue::factory()->for($account)->create([
            'switch_resource_id' => 'switch-queue-delete',
        ]);
        $gateway = $this->mock(SwitchQueueGateway::class);
        $gateway->shouldReceive('replaceRoster')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $agentIds): bool => $received->is($account)
                && $resourceId === 'switch-queue-delete'
                && $agentIds === [],
        )->andReturn(['id' => 'switch-queue-delete', 'agents' => []]);
        $gateway->shouldReceive('delete')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId): bool => $received->is($account)
                && $resourceId === 'switch-queue-delete',
        );

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/queues/{$queue->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($queue);
        $this->assertDatabaseHas('audit_logs', ['action' => 'queue.deleted']);
    }

    public function test_read_only_user_lists_unique_account_agents_with_public_queue_references(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->mock(SwitchAgentGateway::class)->shouldNotReceive('status', 'statistics', 'updateStatus');
        $this->mock(SwitchQueueGateway::class)->shouldNotReceive('capabilities');
        $agent = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Ada Lovelace',
            'extension' => '1001',
            'switch_resource_id' => 'switch-user-1',
        ]);
        $support = SwitchQueue::factory()->for($account)->create(['name' => 'Support']);
        $sales = SwitchQueue::factory()->for($account)->create(['name' => 'Sales']);
        $foreignQueue = SwitchQueue::factory()->create();

        foreach ([$support, $sales] as $queue) {
            $queue->agents()->create([
                'switch_extension_id' => $agent->getKey(),
                'switch_user_resource_id' => 'switch-user-1',
            ]);
        }

        $foreignQueue->agents()->create([
            'switch_extension_id' => SwitchExtension::factory()->create()->getKey(),
            'switch_user_resource_id' => 'private-foreign-agent',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/agents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $agent->id)
            ->assertJsonPath('data.0.name', 'Ada Lovelace')
            ->assertJsonPath('data.0.extension', '1001')
            ->assertJsonCount(2, 'data.0.queues')
            ->assertJsonMissing(['switch-user-1', 'private-foreign-agent']);

        $this->assertStringNotContainsString('switch_resource_id', $response->getContent());
    }

    public function test_options_expose_separate_acdc_capabilities_without_switch_identifiers(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->mock(SwitchQueueGateway::class)
            ->shouldReceive('capabilities')
            ->once()
            ->withArgs(fn (SwitchAccount $received): bool => $received->is($account))
            ->andReturn([
                'configuration_available' => true,
                'live_agent_controls_available' => false,
                'agent_statistics_available' => false,
                'statistics_available' => false,
            ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/queues/options")
            ->assertOk()
            ->assertJsonPath('data.capabilities.configuration_available', true)
            ->assertJsonPath('data.capabilities.live_agent_controls_available', false)
            ->assertJsonPath('data.capabilities.agent_statistics_available', false)
            ->assertJsonPath('data.capabilities.statistics_available', false)
            ->assertJsonMissingPath('data.switch_account_id')
            ->assertDontSee($account->switch_account_id);
    }

    public function test_read_only_user_receives_aggregated_queue_statistics_without_private_switch_data(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $support = SwitchQueue::factory()->for($account)->create([
            'name' => 'Support',
            'switch_resource_id' => 'switch-queue-1',
        ]);
        $sales = SwitchQueue::factory()->for($account)->create([
            'name' => 'Sales',
            'switch_resource_id' => 'switch-queue-2',
        ]);
        $gateway = $this->mock(SwitchQueueGateway::class);
        $gateway->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => true,
            'agent_statistics_available' => false,
            'statistics_available' => true,
        ]);
        $gateway->shouldReceive('statistics')->once()->withArgs(
            fn (SwitchAccount $received): bool => $received->is($account),
        )->andReturn([
            'current_timestamp' => 63800001000,
            'statistics' => [
                ['queue_id' => 'switch-queue-1', 'status' => 'waiting', 'entered_timestamp' => 63800000990, 'wait_time' => null, 'talk_time' => null],
                ['queue_id' => 'switch-queue-1', 'status' => 'processed', 'entered_timestamp' => 63800000000, 'wait_time' => 10, 'talk_time' => 120],
                ['queue_id' => 'switch-queue-2', 'status' => 'abandoned', 'entered_timestamp' => 63800000950, 'wait_time' => 20, 'talk_time' => null],
                ['queue_id' => 'unprojected-private-id', 'status' => 'handled', 'entered_timestamp' => 63800000980, 'wait_time' => 5, 'talk_time' => null],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/queues/statistics")
            ->assertOk()
            ->assertJsonPath('data.totals.waiting', 1)
            ->assertJsonPath('data.totals.processed', 1)
            ->assertJsonPath('data.totals.average_wait_seconds', 12)
            ->assertJsonPath('data.totals.longest_current_wait_seconds', 10)
            ->assertJsonPath('data.unresolved_records', 1)
            ->assertJsonPath('data.queues.0.id', $sales->id)
            ->assertJsonPath('data.queues.1.id', $support->id)
            ->assertJsonMissing(['switch-queue-1', 'switch-queue-2', 'unprojected-private-id'])
            ->assertJsonMissingPath('data.current_timestamp')
            ->assertJsonMissingPath('data.statistics');

        $this->assertStringNotContainsString('caller', $response->getContent());
        $this->assertStringNotContainsString('agent_id', $response->getContent());
    }

    public function test_queue_statistics_stays_disabled_when_switch_capability_is_unavailable(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $gateway = $this->mock(SwitchQueueGateway::class);
        $gateway->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => false,
            'agent_statistics_available' => false,
            'statistics_available' => false,
        ]);
        $gateway->shouldNotReceive('statistics');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/queues/statistics")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Live queue statistics are unavailable for this Switch deployment.',
            ]);
    }

    public function test_read_only_user_receives_projected_agent_statistics_without_private_switch_data(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $agent = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Ada Lovelace',
            'extension' => '1001',
            'switch_resource_id' => 'switch-user-1',
        ]);
        $queue = SwitchQueue::factory()->for($account)->create();
        $queue->agents()->create([
            'switch_extension_id' => $agent->getKey(),
            'switch_user_resource_id' => 'switch-user-1',
        ]);
        $gateway = $this->mock(SwitchQueueGateway::class);
        $gateway->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => true,
            'agent_statistics_available' => true,
            'statistics_available' => false,
        ]);
        $this->mock(SwitchAgentGateway::class)
            ->shouldReceive('statistics')
            ->once()
            ->withArgs(fn (SwitchAccount $received): bool => $received->is($account))
            ->andReturn([
                ['agent_id' => 'switch-user-1', 'total_calls' => 10, 'answered_calls' => 8, 'missed_calls' => 2],
                ['agent_id' => 'unprojected-private-agent', 'total_calls' => 2, 'answered_calls' => 1, 'missed_calls' => 1],
            ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/agents/statistics")
            ->assertOk()
            ->assertJsonPath('data.totals.total_calls', 12)
            ->assertJsonPath('data.totals.answered_calls', 9)
            ->assertJsonPath('data.totals.missed_calls', 3)
            ->assertJsonPath('data.totals.answer_rate_percentage', 75)
            ->assertJsonPath('data.agents.0.id', $agent->id)
            ->assertJsonPath('data.agents.0.name', 'Ada Lovelace')
            ->assertJsonPath('data.agents.0.answer_rate_percentage', 80)
            ->assertJsonPath('data.unresolved_agents', 1)
            ->assertJsonMissing(['switch-user-1', 'unprojected-private-agent'])
            ->assertJsonMissingPath('data.agents.0.agent_id');

        $this->assertStringNotContainsString('queue_id', $response->getContent());
        $this->assertStringNotContainsString('caller', $response->getContent());
    }

    public function test_agent_statistics_stays_disabled_when_its_switch_capability_is_unavailable(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $gateway = $this->mock(SwitchQueueGateway::class);
        $gateway->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => true,
            'agent_statistics_available' => false,
            'statistics_available' => true,
        ]);
        $this->mock(SwitchAgentGateway::class)->shouldNotReceive('statistics');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/agents/statistics")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Live agent statistics are unavailable for this Switch deployment.',
            ]);
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
        $this->mock(SwitchQueueGateway::class)->shouldNotReceive('capabilities');
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
        $this->mock(SwitchQueueGateway::class)->shouldNotReceive('capabilities');
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

    public function test_read_only_user_receives_authoritative_agent_queue_memberships_without_switch_ids(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $agent = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Ada Lovelace',
            'switch_resource_id' => 'switch-user-1',
        ]);
        $support = SwitchQueue::factory()->for($account)->create([
            'name' => 'Support',
            'switch_resource_id' => 'switch-queue-1',
        ]);
        $sales = SwitchQueue::factory()->for($account)->create([
            'name' => 'Sales',
            'switch_resource_id' => 'switch-queue-2',
        ]);
        $support->agents()->create([
            'switch_extension_id' => $agent->getKey(),
            'switch_user_resource_id' => 'switch-user-1',
        ]);
        $this->mock(SwitchQueueGateway::class)->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => true,
            'agent_statistics_available' => false,
            'statistics_available' => false,
        ]);
        $this->mock(SwitchAgentGateway::class)
            ->shouldReceive('queueIds')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $switchUserId): bool => $received->is($account) && $switchUserId === 'switch-user-1')
            ->andReturn(['switch-queue-2', 'unprojected-private-queue']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/agents/{$agent->id}/queues")
            ->assertOk()
            ->assertJsonPath('data.agent.id', $agent->id)
            ->assertJsonPath('data.assigned_queues.0.id', $sales->id)
            ->assertJsonPath('data.available_queues.0.id', $support->id)
            ->assertJsonPath('data.unresolved_queues', 1)
            ->assertJsonMissing(['switch-user-1', 'switch-queue-1', 'switch-queue-2', 'unprojected-private-queue']);

        $this->assertStringNotContainsString('switch_resource_id', $response->getContent());
    }

    public function test_read_only_user_receives_privacy_safe_agent_availability(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $agent = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Ada Lovelace',
            'switch_resource_id' => 'switch-user-1',
        ]);
        $queue = SwitchQueue::factory()->for($account)->create([
            'name' => 'Support',
            'switch_resource_id' => 'switch-queue-1',
        ]);
        $queue->agents()->create([
            'switch_extension_id' => $agent->getKey(),
            'switch_user_resource_id' => 'switch-user-1',
        ]);
        $this->mock(SwitchQueueGateway::class)->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => true,
            'agent_statistics_available' => false,
            'statistics_available' => false,
        ]);
        $this->mock(SwitchAgentGateway::class)
            ->shouldReceive('availability')
            ->once()
            ->withArgs(fn (SwitchAccount $received): bool => $received->is($account))
            ->andReturn([
                ['agent_id' => 'switch-user-1', 'status' => 'ready', 'timestamp' => 63800000000],
                ['agent_id' => 'unprojected-private-agent', 'status' => 'paused', 'timestamp' => 63800000001],
            ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/agents/availability")
            ->assertOk()
            ->assertJsonPath('data.agents.0.id', $agent->id)
            ->assertJsonPath('data.agents.0.status', 'ready')
            ->assertJsonPath('data.agents.0.changed_at', 63800000000)
            ->assertJsonPath('data.unresolved_agents', 1)
            ->assertJsonMissing(['switch-user-1', 'unprojected-private-agent']);

        $this->assertStringNotContainsString('agent_id', $response->getContent());
        $this->assertStringNotContainsString('call_id', $response->getContent());
        $this->assertStringNotContainsString('queue_id', $response->getContent());
    }

    public function test_agent_availability_is_capability_gated_without_calling_switch(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->mock(SwitchQueueGateway::class)->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => false,
            'agent_statistics_available' => false,
            'statistics_available' => false,
        ]);
        $this->mock(SwitchAgentGateway::class)->shouldNotReceive('availability');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/agents/availability")
            ->assertConflict()
            ->assertJsonPath('message', 'Live agent availability is unavailable for this Switch deployment.');
    }

    public function test_operator_requests_audited_agent_queue_login_and_reconciles_known_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $agent = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Ada Lovelace',
            'switch_resource_id' => 'switch-user-1',
        ]);
        $support = SwitchQueue::factory()->for($account)->create([
            'name' => 'Support',
            'switch_resource_id' => 'switch-queue-1',
        ]);
        $sales = SwitchQueue::factory()->for($account)->create([
            'name' => 'Sales',
            'switch_resource_id' => 'switch-queue-2',
        ]);
        $support->agents()->create([
            'switch_extension_id' => $agent->getKey(),
            'switch_user_resource_id' => 'switch-user-1',
        ]);
        $this->mock(SwitchQueueGateway::class)->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => true,
            'agent_statistics_available' => false,
            'statistics_available' => false,
        ]);
        $this->mock(SwitchAgentGateway::class)
            ->shouldReceive('updateQueueMembership')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $switchUserId, string $action, string $switchQueueId): bool => $received->is($account)
                && $switchUserId === 'switch-user-1'
                && $action === 'login'
                && $switchQueueId === 'switch-queue-2')
            ->andReturn(['switch-queue-1', 'switch-queue-2']);

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/agents/{$agent->id}/queues", [
                'action' => 'login',
                'queue_id' => $sales->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_queues.0.id', $sales->id)
            ->assertJsonPath('data.assigned_queues.1.id', $support->id)
            ->assertJsonMissing(['switch-user-1', 'switch-queue-1', 'switch-queue-2']);

        $this->assertDatabaseHas('switch_queue_agents', [
            'switch_queue_id' => $sales->getKey(),
            'switch_extension_id' => $agent->getKey(),
            'switch_user_resource_id' => 'switch-user-1',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent.queue_membership_requested',
            'outcome' => 'succeeded',
            'resource_type' => 'agent',
        ]);
    }

    public function test_final_agent_queue_removal_requires_explicit_confirmation_and_reconciles_agent_lifecycle(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $agent = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Ada Lovelace',
            'switch_resource_id' => 'switch-user-1',
        ]);
        $support = SwitchQueue::factory()->for($account)->create([
            'name' => 'Support',
            'switch_resource_id' => 'switch-queue-1',
        ]);
        $support->agents()->create([
            'switch_extension_id' => $agent->getKey(),
            'switch_user_resource_id' => 'switch-user-1',
        ]);
        $this->mock(SwitchQueueGateway::class)->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => true,
            'agent_statistics_available' => false,
            'statistics_available' => false,
        ]);
        $gateway = $this->mock(SwitchAgentGateway::class);
        $gateway->shouldReceive('queueIds')
            ->twice()
            ->withArgs(fn (SwitchAccount $received, string $switchUserId): bool => $received->is($account)
                && $switchUserId === 'switch-user-1')
            ->andReturn(['switch-queue-1']);
        $gateway->shouldReceive('updateQueueMembership')
            ->once()
            ->withArgs(fn (SwitchAccount $received, string $switchUserId, string $action, string $switchQueueId): bool => $received->is($account)
                && $switchUserId === 'switch-user-1'
                && $action === 'logout'
                && $switchQueueId === 'switch-queue-1')
            ->andReturn([]);

        $endpoint = "/api/v1/accounts/{$account->id}/agents/{$agent->id}/queues";

        $this->actingAs($user)
            ->postJson($endpoint, ['action' => 'logout', 'queue_id' => $support->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm_last_queue');

        $this->assertDatabaseHas('switch_queue_agents', [
            'switch_queue_id' => $support->getKey(),
            'switch_extension_id' => $agent->getKey(),
        ]);

        $this->actingAs($user)
            ->postJson($endpoint, [
                'action' => 'logout',
                'queue_id' => $support->id,
                'confirm_last_queue' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_queues', [])
            ->assertJsonPath('data.agent_active', false)
            ->assertJsonMissing(['switch-user-1', 'switch-queue-1']);

        $this->assertDatabaseMissing('switch_queue_agents', [
            'switch_queue_id' => $support->getKey(),
            'switch_extension_id' => $agent->getKey(),
        ]);
    }

    public function test_agent_queue_membership_mutation_is_gated_when_live_controls_are_unavailable(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $agent = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $queue = SwitchQueue::factory()->for($account)->create(['switch_resource_id' => 'switch-queue-1']);
        $queue->agents()->create([
            'switch_extension_id' => $agent->getKey(),
            'switch_user_resource_id' => 'switch-user-1',
        ]);
        $this->mock(SwitchQueueGateway::class)->shouldReceive('capabilities')->once()->andReturn([
            'configuration_available' => true,
            'live_agent_controls_available' => false,
            'agent_statistics_available' => false,
            'statistics_available' => false,
        ]);
        $this->mock(SwitchAgentGateway::class)->shouldNotReceive('updateQueueMembership');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/agents/{$agent->id}/queues", [
                'action' => 'logout',
                'queue_id' => $queue->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('queue_id');
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
