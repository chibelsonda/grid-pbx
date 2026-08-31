<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\LineKeys\Dto\LineKeyWriteData;
use GridPbx\Switch\Domains\LineKeys\LineKeyResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class LineKeyResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_reads_typed_device_line_keys(): void
    {
        $client = $this->client(['data' => $this->deviceData()]);
        $snapshot = $client->get('account-1', 'device-1');

        self::assertSame('Yealink', $snapshot->brand);
        self::assertSame('T5', $snapshot->family);
        self::assertCount(2, $snapshot->lineKeys);
        self::assertNull($snapshot->lineKeys[0]->value);
        self::assertNull($snapshot->lineKeys[0]->label);
        self::assertSame('Reception', $snapshot->lineKeys[1]->label);
        self::assertSame('GET', $this->history[0]['request']->getMethod());
    }

    public function test_it_replaces_line_key_maps_while_preserving_the_device_document(): void
    {
        $client = $this->clientWithPayloads([
            ['data' => $this->deviceData()],
            ['data' => $this->deviceData()],
        ]);
        $client->update('account-1', 'device-1', [
            new LineKeyWriteData('combo', 0, 'line'),
            new LineKeyWriteData('feature', 2, 'parking', '3', 'Parking 3'),
        ]);

        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/devices/device-1', $this->history[1]['request']->getUri()->getPath());
        self::assertSame([
            'data' => [
                'name' => 'Reception phone',
                'vendor_device' => ['managed' => true],
                'provision' => [
                    'endpoint_brand' => 'Yealink',
                    'endpoint_family' => 'T5',
                    'endpoint_model' => 'T54W',
                    'vendor_options' => ['theme' => 'dark'],
                    'combo_keys' => ['0' => [
                        'vendor_key' => ['color' => 'green'],
                        'type' => 'line',
                    ]],
                    'feature_keys' => ['2' => [
                        'vendor_key' => ['color' => 'red'],
                        'type' => 'parking',
                        'value' => ['vendor_value' => true, 'label' => 'Parking 3', 'value' => 3],
                    ]],
                ],
            ],
        ], json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_clears_a_modeled_value_without_removing_unknown_key_fields(): void
    {
        $client = $this->clientWithPayloads([
            ['data' => $this->deviceData()],
            ['data' => $this->deviceData()],
        ]);
        $client->update('account-1', 'device-1', [
            new LineKeyWriteData('combo', 0, 'line'),
        ]);

        $body = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([
            'vendor_key' => ['color' => 'green'],
            'type' => 'line',
        ], $body['data']['provision']['combo_keys']['0']);
        self::assertSame([], $body['data']['provision']['feature_keys']);
    }

    public function test_it_rejects_values_that_do_not_match_the_selected_key_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LineKeyWriteData('combo', 0, 'speed_dial', 1001);
    }

    public function test_it_rejects_a_label_without_a_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LineKeyWriteData('feature', 1, 'presence', null, 'Reception');
    }

    public function test_it_rejects_a_line_appearance_on_a_feature_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LineKeyWriteData('feature', 1, 'line');
    }

    public function test_it_rejects_an_assignment_without_a_required_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LineKeyWriteData('feature', 1, 'speed_dial');
    }

    /** @return array<string, mixed> */
    private function deviceData(): array
    {
        return [
            'id' => 'device-1',
            'name' => 'Reception phone',
            'vendor_device' => ['managed' => true],
            'provision' => [
                'endpoint_brand' => 'Yealink',
                'endpoint_family' => 'T5',
                'endpoint_model' => 'T54W',
                'vendor_options' => ['theme' => 'dark'],
                'combo_keys' => ['0' => [
                    'vendor_key' => ['color' => 'green'],
                    'type' => 'line',
                    'value' => '1001',
                ]],
                'feature_keys' => ['2' => [
                    'vendor_key' => ['color' => 'red'],
                    'type' => 'speed_dial',
                    'value' => ['vendor_value' => true, 'label' => 'Reception', 'value' => '1000'],
                ]],
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function client(array $payload): LineKeyResourceClient
    {
        return $this->clientWithPayloads([$payload]);
    }

    /** @param list<array<string, mixed>> $payloads */
    private function clientWithPayloads(array $payloads): LineKeyResourceClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler(array_map(
            static fn (array $payload): Response => new Response(
                200,
                [],
                json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR),
            ),
            $payloads,
        )));
        $stack->push(Middleware::history($this->history));
        $switch = new SwitchClient(
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

        return new LineKeyResourceClient($switch);
    }
}
