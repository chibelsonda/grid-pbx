<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests\Domains\Billing;

use GridPbx\Switch\Domains\Billing\BillingResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class BillingResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_maps_read_only_ledger_totals_summaries_and_transactions(): void
    {
        $client = new BillingResourceClient($this->switchWithResponses([
            $this->response(['data' => [
                'per-minute-voip' => [
                    'amount' => '-54.7404',
                    'usage' => ['quantity' => 14520, 'type' => 'voice', 'unit' => 'sec'],
                ],
            ]]),
            $this->response(['data' => ['amount' => '-44.5604']]),
            $this->response(['data' => [[
                'id' => 'transaction-1',
                'amount' => '10.18',
                'reason' => 'database_rollup',
                'type' => 'credit',
                'description' => 'monthly rollup',
                'created' => 63598331974,
                'version' => 2,
                'code' => 9999,
            ]]]),
        ]));

        $snapshot = $client->snapshot('account-1');

        self::assertTrue($snapshot->ledgersAvailable);
        self::assertTrue($snapshot->ledgerTotalAvailable);
        self::assertTrue($snapshot->transactionsAvailable);
        self::assertSame('-44.5604', $snapshot->ledgerTotal);
        self::assertSame('per-minute-voip', $snapshot->ledgers[0]->sourceService);
        self::assertSame('-54.7404', $snapshot->ledgers[0]->amount);
        self::assertSame('14520', $snapshot->ledgers[0]->usageQuantity);
        self::assertSame('transaction-1', $snapshot->transactions[0]->id);
        self::assertSame('10.18', $snapshot->transactions[0]->amount);
        self::assertSame(
            [
                '/v2/accounts/account-1/ledgers',
                '/v2/accounts/account-1/ledgers/total',
                '/v2/accounts/account-1/transactions',
            ],
            array_map(fn (array $entry): string => $entry['request']->getUri()->getPath(), $this->history),
        );
        self::assertSame(
            ['GET', 'GET', 'GET'],
            array_map(fn (array $entry): string => $entry['request']->getMethod(), $this->history),
        );
    }

    public function test_marks_missing_version_specific_endpoints_unavailable(): void
    {
        $client = new BillingResourceClient($this->switchWithResponses([
            new Response(404),
            new Response(404),
            $this->response(['data' => []]),
        ]));

        $snapshot = $client->snapshot('account-1');

        self::assertFalse($snapshot->ledgersAvailable);
        self::assertFalse($snapshot->ledgerTotalAvailable);
        self::assertTrue($snapshot->transactionsAvailable);
        self::assertNull($snapshot->ledgerTotal);
        self::assertSame([], $snapshot->ledgers);
        self::assertSame([], $snapshot->transactions);
    }

    public function test_does_not_hide_authorization_failures_as_unsupported_endpoints(): void
    {
        $client = new BillingResourceClient($this->switchWithResponses([new Response(403)]));

        $this->expectException(SwitchRequestException::class);
        $this->expectExceptionMessage('Switch request failed.');

        $client->snapshot('account-1');
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SwitchClient(
            new Client(['handler' => $stack]),
            new SwitchConfig('http://switch.test/v2', 'unused'),
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
