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

    public function test_acdc_capabilities_are_probed_independently_without_returning_switch_data(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [['id' => 'private-queue-id']]]),
            new Response(500, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
            new Response(503, [], json_encode(['status' => 'error'], JSON_THROW_ON_ERROR)),
        ]);

        $capabilities = (new AcdcCapabilityClient($switch))->discover('account-1');

        self::assertSame([
            'configuration_available' => true,
            'live_agent_controls_available' => false,
            'statistics_available' => false,
        ], $capabilities->toArray());
        self::assertSame('/v2/accounts/account-1/queues', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('paginate=true&page_size=1', $this->history[0]['request']->getUri()->getQuery());
        self::assertSame('/v2/accounts/account-1/agents/status', $this->history[1]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account-1/queues/stats', $this->history[2]['request']->getUri()->getPath());
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
