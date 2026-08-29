<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use GridPbx\Switch\Domains\Provisioning\ProvisionerClient;
use GridPbx\Switch\Domains\Provisioning\ProvisionerConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProvisionerClientTest extends TestCase
{
    /** @return iterable<string, array{ProvisionerConfig, string, string}> */
    public static function authenticatedConfigurations(): iterable
    {
        yield 'bearer' => [
            new ProvisionerConfig('https://provisioner.test/api', 'bearer', token: 'secret'),
            'Authorization',
            'Bearer secret',
        ];
        yield 'token header' => [
            new ProvisionerConfig(
                'https://provisioner.test/api',
                'header',
                token: 'secret',
                headerName: 'X-Api-Key',
            ),
            'X-Api-Key',
            'secret',
        ];
    }

    #[DataProvider('authenticatedConfigurations')]
    public function test_it_authenticates_catalog_requests(
        ProvisionerConfig $config,
        string $header,
        string $expected,
    ): void {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"data":{}}'),
        ]));
        $stack->push(Middleware::history($history));

        $payload = (new ProvisionerClient(new Client(['handler' => $stack]), $config))->get('phones');

        self::assertSame(['data' => []], $payload);
        self::assertSame($expected, $history[0]['request']->getHeaderLine($header));
    }

    public function test_it_supports_basic_authentication(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"data":{}}'),
        ]));
        $stack->push(Middleware::history($history));
        $config = new ProvisionerConfig(
            'https://provisioner.test/api',
            'basic',
            username: 'client',
            password: 'password',
        );

        (new ProvisionerClient(new Client(['handler' => $stack]), $config))->get('phones');

        self::assertSame(
            'Basic '.base64_encode('client:password'),
            $history[0]['request']->getHeaderLine('Authorization'),
        );
    }

    public function test_it_does_not_leak_a_provider_error_body(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(401, [], '{"message":"secret provider detail"}'),
        ]));
        $client = new ProvisionerClient(
            new Client(['handler' => $stack]),
            new ProvisionerConfig('https://provisioner.test/api'),
        );

        try {
            $client->get('phones');
            self::fail('Expected a provisioner request exception.');
        } catch (SwitchRequestException $exception) {
            self::assertSame(401, $exception->statusCode);
            self::assertSame('Provisioner request failed.', $exception->getMessage());
            self::assertStringNotContainsString('secret provider detail', $exception->getMessage());
        }
    }
}
