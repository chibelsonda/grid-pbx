<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Dto\Agents\AgentQueueMembershipWriteData;
use GridPbx\Switch\Dto\Agents\AgentStatusWriteData;
use GridPbx\Switch\Dto\Queues\QueueWriteData;
use GridPbx\Switch\Resources\AgentResourceClient;
use GridPbx\Switch\Resources\QueueResourceClient;
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
        ));
        $client->replaceRoster('account-1', 'queue-1', ['agent-1', 'agent-2']);
        $createBody = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $rosterBody = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('most_idle', $queue->strategy);
        self::assertSame('media-1', $queue->musicOnHoldMediaId);
        self::assertArrayNotHasKey('agents', $createBody['data']);
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
