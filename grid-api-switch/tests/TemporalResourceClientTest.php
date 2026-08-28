<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Dto\Callflows\CallflowCreateData;
use GridPbx\Switch\Dto\TemporalRules\TemporalRuleWriteData;
use GridPbx\Switch\Dto\TemporalRuleSets\TemporalRuleSetWriteData;
use GridPbx\Switch\Resources\TemporalRuleResourceClient;
use GridPbx\Switch\Resources\TemporalRuleSetResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class TemporalResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_rule_client_maps_schedule_and_writes_canonical_weekdays(): void
    {
        $client = new TemporalRuleResourceClient($this->switchWithResponses([
            $this->response(['data' => [['id' => 'rule-1', 'name' => 'Business hours']]]),
            $this->response(['data' => [
                'id' => 'rule-1', 'name' => 'Business hours', 'cycle' => 'weekly',
                'interval' => 1, 'wdays' => ['monday', 'wensday'],
                'time_window_start' => 32400, 'time_window_stop' => 61200,
            ]]),
            $this->response(['data' => ['id' => 'rule-2', 'name' => 'Weekend', 'cycle' => 'weekly']]),
        ]));

        $rules = iterator_to_array($client->allDetails('account-1'), false);
        $client->create('account-1', new TemporalRuleWriteData(name: 'Weekend', cycle: 'weekly', weekdays: ['saturday', 'sunday']));
        $body = json_decode((string) $this->history[2]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['monday', 'wednesday'], $rules[0]->weekdays);
        self::assertSame(32400, $rules[0]->timeWindowStart);
        self::assertSame(['saturday', 'sunday'], $body['data']['wdays']);
    }

    public function test_rule_set_client_preserves_ordered_rule_membership(): void
    {
        $client = new TemporalRuleSetResourceClient($this->switchWithResponses([
            $this->response(['data' => ['id' => 'set-1', 'name' => 'Office schedule', 'temporal_rules' => ['rule-2', 'rule-1']]]),
        ]));
        $set = $client->create('account-1', new TemporalRuleSetWriteData('Office schedule', ['rule-2', 'rule-1']));
        $body = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['rule-2', 'rule-1'], $set->temporalRuleIds);
        self::assertSame(['rule-2', 'rule-1'], $body['data']['temporal_rules']);
    }

    public function test_rule_client_uses_nullable_patch_for_operational_overrides(): void
    {
        $client = new TemporalRuleResourceClient($this->switchWithResponses([
            $this->response(['data' => ['id' => 'rule-1', 'name' => 'Business hours', 'cycle' => 'weekly', 'enabled' => true]]),
            $this->response(['data' => ['id' => 'rule-1', 'name' => 'Business hours', 'cycle' => 'weekly']]),
        ]));

        self::assertTrue($client->setOverride('account-1', 'rule-1', true)->enabled);
        self::assertNull($client->setOverride('account-1', 'rule-1', null)->enabled);
        self::assertSame('PATCH', $this->history[0]['request']->getMethod());
        self::assertSame(['data' => ['enabled' => true]], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(['data' => ['enabled' => null]], json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_guided_temporal_callflow_uses_rule_set_payload_key(): void
    {
        $data = (new CallflowCreateData('Office routing', 'temporal_route', 'set-1', ['+15550001000']))->toSwitchData();

        self::assertSame('temporal_route', $data['flow']['module']);
        self::assertSame(['rule_set' => 'set-1'], $data['flow']['data']);
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SwitchClient(new Client(['handler' => $stack]), new SwitchConfig('http://switch.test/v2', 'unused'), new class implements TokenProvider {
            public function token(): string { return 'test-token'; }
            public function invalidate(): void {}
        });
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
