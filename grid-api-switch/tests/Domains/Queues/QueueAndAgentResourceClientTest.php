<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Agents\AgentResourceClient;
use GridPbx\Switch\Domains\Agents\Dto\AgentQueueMembershipWriteData;
use GridPbx\Switch\Domains\Agents\Dto\AgentStatusWriteData;
use GridPbx\Switch\Domains\Queues\AcdcCapabilityClient;
use GridPbx\Switch\Domains\Queues\Dto\QueueAnnouncementsWriteData;
use GridPbx\Switch\Domains\Queues\Dto\QueueWriteData;
use GridPbx\Switch\Domains\Queues\QueueResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class QueueAndAgentResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_queue_write_data_omits_an_unset_optional_music_on_hold_id(): void
    {
        $data = (new QueueWriteData(name: 'Support'))->toSwitchData();

        self::assertArrayNotHasKey('moh', $data);
    }

    public function test_queue_client_maps_settings_and_replaces_the_roster_separately(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'id' => 'queue-1',
                'name' => 'Support',
                'strategy' => 'most_idle',
                'agent_ring_timeout' => 20,
                'agent_wrapup_time' => 10,
                'connection_timeout' => 120,
                'max_queue_size' => 25,
                'ring_simultaneously' => 2,
                'enter_when_empty' => false,
                'record_caller' => true,
                'caller_exit_key' => '*',
                'moh' => 'media-1',
                'announce' => 'media-2',
                'max_priority' => 20,
                'announcements' => [
                    'interval' => 45,
                    'position_announcements_enabled' => true,
                    'wait_time_announcements_enabled' => false,
                ],
                'cdr_url' => 'https://cdr.example.test/events',
                'recording_url' => 'https://recordings.example.test/audio',
            ]]),
            $this->response(['data' => ['agent-1', 'agent-2']]),
        ]);
        $client = new QueueResourceClient($switch);

        $queue = $client->create('account-1', new QueueWriteData(
            name: 'Support',
            strategy: 'most_idle',
            agentRingTimeout: 20,
            agentWrapupTime: 10,
            connectionTimeout: 120,
            maxQueueSize: 25,
            ringSimultaneously: 2,
            enterWhenEmpty: false,
            recordCaller: true,
            callerExitKey: '*',
            musicOnHoldMediaId: 'media-1',
            announceMediaId: 'media-2',
            maxPriority: 20,
            announcements: new QueueAnnouncementsWriteData(
                interval: 45,
                positionAnnouncementsEnabled: true,
            ),
            cdrUrl: 'https://cdr.example.test/events',
            recordingUrl: 'https://recordings.example.test/audio',
        ));
        $client->replaceRoster('account-1', 'queue-1', ['agent-1', 'agent-2']);
        $createBody = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $rosterBody = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('most_idle', $queue->strategy);
        self::assertSame('media-1', $queue->musicOnHoldMediaId);
        self::assertSame('media-2', $queue->announceMediaId);
        self::assertSame(20, $queue->maxPriority);
        self::assertSame(45, $queue->announcements?->interval());
        self::assertSame('https://cdr.example.test/events', $queue->cdrUrl);
        self::assertSame('https://recordings.example.test/audio', $createBody['data']['recording_url']);
        self::assertArrayNotHasKey('agents', $createBody['data']);
        self::assertSame(45, $createBody['data']['announcements']['interval']);
        self::assertSame(['agent-1', 'agent-2'], $rosterBody['data']);
        self::assertSame('/v2/accounts/account-1/queues/queue-1/roster', $this->history[1]['request']->getUri()->getPath());
    }

    public function test_queue_update_preserves_safe_unknown_and_policy_hidden_fields(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'id' => 'queue-1',
                'name' => 'Support',
                'max_priority' => 25,
                'cdr_url' => 'https://cdr.example.test/events',
                'recording_url' => 'https://recordings.example.test/audio',
                'call_recording_url' => 'https://runtime.example.test/audio',
                'announcements' => [
                    'interval' => 30,
                    'vendor_mode' => 'managed',
                    'media' => [
                        'in_the_queue' => 'old-media-1',
                        'increase_in_call_volume' => 'old-media-2',
                        'the_estimated_wait_time_is' => 'old-media-3',
                        'you_are_at_position' => 'old-media-4',
                        'future_prompt' => 'keep-media',
                    ],
                ],
                'future_option' => ['nested' => 'keep'],
                'agents' => ['private-user-1'],
                'pvt_secret' => 'discard',
                '_rev' => '1-private',
            ]]),
            $this->response(['data' => [
                'id' => 'queue-1',
                'name' => 'Updated support',
                'max_priority' => 25,
            ]]),
        ]);
        $client = new QueueResourceClient($switch);

        $client->update('account-1', 'queue-1', new QueueWriteData(
            name: 'Updated support',
            maxPriority: 99,
            cdrUrl: 'https://attempted.example.test/events',
            recordingUrl: 'https://attempted.example.test/audio',
            announcements: new QueueAnnouncementsWriteData(
                interval: 45,
                positionAnnouncementsEnabled: true,
                inTheQueueMediaId: 'new-media-1',
                increaseInCallVolumeMediaId: 'new-media-2',
                estimatedWaitTimeMediaId: 'new-media-3',
                positionMediaId: 'new-media-4',
            ),
        ));
        $body = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        self::assertSame(25, $body['data']['max_priority']);
        self::assertSame('https://cdr.example.test/events', $body['data']['cdr_url']);
        self::assertSame('https://recordings.example.test/audio', $body['data']['recording_url']);
        self::assertSame('https://runtime.example.test/audio', $body['data']['call_recording_url']);
        self::assertSame('keep', $body['data']['future_option']['nested']);
        self::assertSame('managed', $body['data']['announcements']['vendor_mode']);
        self::assertSame('keep-media', $body['data']['announcements']['media']['future_prompt']);
        self::assertSame('new-media-4', $body['data']['announcements']['media']['you_are_at_position']);
        self::assertArrayNotHasKey('agents', $body['data']);
        self::assertArrayNotHasKey('pvt_secret', $body['data']);
        self::assertArrayNotHasKey('_rev', $body['data']);
        self::assertArrayNotHasKey('id', $body['data']);
    }

    public function test_queue_write_data_enforces_documented_gridpbx_safety_limits(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QueueWriteData(name: 'Support', ringSimultaneously: 101);
    }

    public function test_queue_statistics_discards_private_call_data_and_maps_public_and_event_keys(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'current_timestamp' => 63800001000,
                'stats' => [
                    [
                        'queue_id' => 'queue-1',
                        'status' => 'processed',
                        'entered_timestamp' => 63800000000,
                        'wait_time' => 20,
                        'talk_time' => 180,
                        'call_id' => 'private-call-id',
                        'agent_id' => 'private-agent-id',
                        'caller_id_number' => '+15551234567',
                    ],
                    [
                        'Queue-ID' => 'queue-2',
                        'Status' => 'waiting',
                        'Entered-Timestamp' => 63800000990,
                        'Call-ID' => 'private-call-id-2',
                    ],
                ],
            ]]),
        ]);

        $snapshot = (new QueueResourceClient($switch))->statistics('account-1')->toArray();

        self::assertSame('/v2/accounts/account-1/queues/stats', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('processed', $snapshot['statistics'][0]['status']);
        self::assertSame(180, $snapshot['statistics'][0]['talk_time']);
        self::assertSame('waiting', $snapshot['statistics'][1]['status']);
        self::assertSame(63800000990, $snapshot['statistics'][1]['entered_timestamp']);
        self::assertArrayNotHasKey('call_id', $snapshot['statistics'][0]);
        self::assertArrayNotHasKey('agent_id', $snapshot['statistics'][0]);
        self::assertArrayNotHasKey('caller_id_number', $snapshot['statistics'][0]);
    }

    public function test_queue_statistics_rejects_an_unknown_status(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'current_timestamp' => 63800001000,
                'stats' => [['queue_id' => 'queue-1', 'status' => 'future-status']],
            ]]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);

        (new QueueResourceClient($switch))->statistics('account-1');
    }

    public function test_agent_client_maps_user_memberships_and_sends_operational_commands(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [[
                'id' => 'user-1',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'queues' => ['queue-1'],
            ]]]),
            $this->response(['data' => ['status' => 'logged_in', 'timestamp' => 63800000000]]),
            $this->response(['data' => 'status update sent']),
            $this->response(['data' => ['queue-1', 'queue-2']]),
        ]);
        $client = new AgentResourceClient($switch);

        $agents = $client->all('account-1');
        $status = $client->status('account-1', 'user-1');
        $client->updateStatus('account-1', 'user-1', new AgentStatusWriteData('pause', 60));
        $queues = $client->updateQueueMembership('account-1', 'user-1', new AgentQueueMembershipWriteData('login', 'queue-2'));
        $statusBody = json_decode((string) $this->history[2]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $queueBody = json_decode((string) $this->history[3]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['queue-1'], $agents[0]->queueIds);
        self::assertSame('logged_in', $status->status);
        self::assertSame(['status' => 'pause', 'timeout' => 60], $statusBody['data']);
        self::assertSame(['action' => 'login', 'queue_id' => 'queue-2'], $queueBody['data']);
        self::assertSame(['queue-1', 'queue-2'], $queues);
    }

    public function test_agent_client_reads_queue_memberships_and_rejects_invalid_identifiers(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => ['queue-1', 'queue-2']]),
            $this->response(['data' => ['queue-1', '']]),
        ]);
        $client = new AgentResourceClient($switch);

        self::assertSame(['queue-1', 'queue-2'], $client->queueIds('account-1', 'user-1'));

        $this->expectException(InvalidSwitchPayloadException::class);
        $client->queueIds('account-1', 'user-1');
    }

    public function test_agent_statistics_keep_only_valid_aggregate_counts(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'private-agent-1' => [
                    'total_calls' => 12,
                    'answered_calls' => 9,
                    'missed_calls' => 3,
                    'queues' => [
                        'private-queue-1' => [
                            'total_calls' => 12,
                            'answered_calls' => 9,
                            'missed_calls' => 3,
                        ],
                    ],
                    'caller_id_number' => '+15551234567',
                ],
            ]]),
        ]);

        $statistics = (new AgentResourceClient($switch))->statistics('account-1')->toArray();

        self::assertSame('/v2/accounts/account-1/agents/stats', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([[
            'agent_id' => 'private-agent-1',
            'total_calls' => 12,
            'answered_calls' => 9,
            'missed_calls' => 3,
        ]], $statistics);
        self::assertArrayNotHasKey('queues', $statistics[0]);
        self::assertArrayNotHasKey('caller_id_number', $statistics[0]);
    }

    public function test_agent_statistics_reject_inconsistent_counts(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [
                'private-agent-1' => [
                    'total_calls' => 2,
                    'answered_calls' => 3,
                ],
            ]]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);

        (new AgentResourceClient($switch))->statistics('account-1');
    }

    public function test_acdc_capabilities_are_probed_independently_without_returning_switch_data(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [['id' => 'private-queue-id']]]),
            new Response(500, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
            new Response(503, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
            new Response(404, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
        ]);

        $capabilities = (new AcdcCapabilityClient($switch))->discover('account-1');

        self::assertSame([
            'configuration_available' => true,
            'live_agent_controls_available' => false,
            'agent_statistics_available' => false,
            'statistics_available' => false,
        ], $capabilities->toArray());
        self::assertSame('/v2/accounts/account-1/queues', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('paginate=true&page_size=1', $this->history[0]['request']->getUri()->getQuery());
        self::assertSame('/v2/accounts/account-1/agents/status', $this->history[1]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account-1/agents/stats', $this->history[2]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account-1/queues/stats', $this->history[3]['request']->getUri()->getPath());
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SwitchClient(
            new Client(['handler' => $stack]),
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            new class implements TokenProvider
            {
                public function token(): string
                {
                    return 'test-token';
                }

                public function invalidate(): void {}
            },
        );
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
