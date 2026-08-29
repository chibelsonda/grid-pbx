<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Provisioning\ProvisionerClient;
use GridPbx\Switch\Domains\Provisioning\ProvisionerConfig;
use GridPbx\Switch\Domains\Provisioning\ProvisioningCatalogResourceClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ProvisioningCatalogResourceClientTest extends TestCase
{
    public function test_it_reads_the_monster_compatible_phone_catalog(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => [
                    'yealink' => [
                        'name' => 'Yealink',
                        'families' => [
                            't5' => [
                                'name' => 'T5',
                                'models' => ['t54w' => [
                                    'id' => 'yealink_t5_t54w',
                                    'name' => 'T54W',
                                    'max_keys' => 10,
                                    'max_exp_modules' => '3',
                                    'max_keys_exp_module' => 20,
                                    'key_types' => ['line', 'presence', 'unsafe_type'],
                                    'value_sources' => ['extensions', 'devices', 'DROP TABLE'],
                                    'ztp_manufacturer' => 'yealink-rps',
                                ]],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(Middleware::history($history));
        $provisioner = new ProvisionerClient(
            new Client(['handler' => $stack]),
            new ProvisionerConfig(
                baseUrl: 'https://provisioner.test/api',
                authType: 'bearer',
                token: 'test-token',
            ),
        );

        $brands = (new ProvisioningCatalogResourceClient($provisioner))->all();

        self::assertSame('yealink', $brands[0]->id);
        self::assertSame('t5', $brands[0]->families[0]->id);
        self::assertSame('t54w', $brands[0]->families[0]->models[0]->id);
        self::assertSame('yealink_t5_t54w', $brands[0]->families[0]->models[0]->templateId);
        self::assertSame(10, $brands[0]->families[0]->models[0]->maxKeys);
        self::assertSame(3, $brands[0]->families[0]->models[0]->maxExpansionModules);
        self::assertSame(20, $brands[0]->families[0]->models[0]->keysPerExpansionModule);
        self::assertSame(['line', 'presence'], $brands[0]->families[0]->models[0]->supportedKeyTypes);
        self::assertSame(['extensions', 'devices'], $brands[0]->families[0]->models[0]->valueSources);
        self::assertSame('yealink-rps', $brands[0]->families[0]->models[0]->manufacturerProvider);
        self::assertSame('/api/phones', $history[0]['request']->getUri()->getPath());
        self::assertSame('Bearer test-token', $history[0]['request']->getHeaderLine('Authorization'));
        self::assertSame('', $history[0]['request']->getHeaderLine('X-Auth-Token'));
    }
}
