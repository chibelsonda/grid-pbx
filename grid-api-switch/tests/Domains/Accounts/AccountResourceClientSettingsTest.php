<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Accounts\Dto\AccountCallerIdWriteData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountCallRecordingData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountCallRestrictionsData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountDialPlanData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountDialPlanRuleData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountEnabledWriteData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountFormatterRuleData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountFormattersData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountMetaflowsData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountPreflowData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountRecordingParametersData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountRecordingRulesData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountRecordingSourceData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountSettingsWriteData;
use GridPbx\Switch\Domains\Accounts\Dto\MusicOnHoldWriteData;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AccountResourceClientSettingsTest extends TestCase
{
    public function test_account_routing_defaults_have_schema_correct_clear_payloads(): void
    {
        self::assertSame(['always' => ''], (new AccountPreflowData)->toSwitchData());
        self::assertInstanceOf(\stdClass::class, (new AccountMetaflowsData)->toSwitchData());

        $settings = new AccountSettingsWriteData(
            name: 'Support',
            organizationName: null,
            timezone: null,
            language: null,
            callWaitingEnabled: true,
            doNotDisturbEnabled: false,
            outboundPrivacy: null,
            showRate: false,
            internalRingtone: null,
            externalRingtone: null,
            callerId: new AccountCallerIdWriteData(null, null, null, null, null, null),
        );

        self::assertNull($settings->toSwitchData()['caller_id_options']['outbound_privacy']);
    }

    public function test_it_reads_and_updates_account_music_on_hold(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response(['data' => [
                'id' => 'account-1',
                'name' => 'Support',
                'music_on_hold' => ['media_id' => 'media-old'],
            ]]),
            $this->response(['data' => [
                'id' => 'account-1',
                'name' => 'Support',
                'music_on_hold' => ['media_id' => 'media-new'],
            ]]),
            $this->response(['data' => [
                'id' => 'account-1',
                'name' => 'Support',
                'music_on_hold' => ['media_id' => ''],
            ]]),
        ]));
        $stack->push(Middleware::history($history));
        $switch = new SwitchClient(
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
        $client = new AccountResourceClient($switch);

        $current = $client->account('account-1');
        $updated = $client->updateMusicOnHold('account-1', new MusicOnHoldWriteData('media-new'));
        $cleared = $client->updateMusicOnHold('account-1', new MusicOnHoldWriteData(null));

        self::assertSame('media-old', $current->musicOnHoldMediaId);
        self::assertSame('media-new', $updated->musicOnHoldMediaId);
        self::assertNull($cleared->musicOnHoldMediaId);
        self::assertSame('PATCH', $history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1', $history[1]['request']->getUri()->getPath());
        self::assertSame(
            ['data' => ['music_on_hold' => ['media_id' => 'media-new']]],
            json_decode((string) $history[1]['request']->getBody(), true),
        );
        self::assertSame(
            ['data' => ['music_on_hold' => ['media_id' => '']]],
            json_decode((string) $history[2]['request']->getBody(), true),
        );
    }

    public function test_it_updates_only_the_typed_account_settings_and_hydrates_the_snapshot(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response(['data' => [
                'id' => 'account-1',
                'name' => 'Support',
                'org' => 'Grid Corp',
                'realm' => 'support.example.test',
                'timezone' => 'Asia/Manila',
                'language' => 'en-US',
                'enabled' => true,
                'call_waiting' => ['enabled' => false],
                'do_not_disturb' => ['enabled' => true],
                'caller_id' => [
                    'internal' => ['name' => 'Support', 'number' => '1000'],
                    'external' => ['name' => 'Grid Support', 'number' => '+15550001000'],
                    'emergency' => ['name' => 'Grid Emergency', 'number' => '+15550001911'],
                ],
                'caller_id_options' => ['outbound_privacy' => 'number', 'show_rate' => true],
                'ringtones' => ['internal' => 'ring-1', 'external' => 'ring-2'],
                'call_restriction' => ['international' => ['action' => 'deny']],
                'call_recording' => [
                    'account' => [
                        'any' => [
                            'offnet' => ['enabled' => true, 'format' => 'wav'],
                        ],
                    ],
                ],
                'dial_plan' => [
                    'system' => ['north_america'],
                    '^([2-9][0-9]{6})$' => ['description' => 'Local', 'prefix' => '+1555'],
                ],
                'formatters' => [
                    'request' => [[
                        'direction' => 'both',
                        'regex' => '^sip:(.*)$',
                        'prefix' => 'tel:',
                    ]],
                ],
                'preflow' => ['always' => 'callflow-1'],
                'metaflows' => [
                    'binding_digit' => '#',
                    'digit_timeout' => 2500,
                    'listen_on' => 'self',
                    'numbers' => ['3' => ['module' => 'hangup', 'data' => [], 'children' => []]],
                ],
            ]]),
        ]));
        $stack->push(Middleware::history($history));
        $switch = new SwitchClient(
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

        $snapshot = (new AccountResourceClient($switch))->updateSettings(
            'account-1',
            new AccountSettingsWriteData(
                'Support',
                'Grid Corp',
                'Asia/Manila',
                'en-US',
                false,
                true,
                'number',
                true,
                'ring-1',
                'ring-2',
                new AccountCallerIdWriteData('Support', '1000', 'Grid Support', '+15550001000', 'Grid Emergency', '+15550001911'),
                new AccountCallRestrictionsData(['international' => 'deny']),
                new AccountCallRecordingData(
                    account: new AccountRecordingRulesData(
                        any: new AccountRecordingSourceData(
                            offnet: new AccountRecordingParametersData(
                                enabled: true,
                                format: 'wav',
                                minimumSeconds: 5,
                                recordOnAnswer: true,
                                recordOnBridge: false,
                                sampleRate: 16000,
                                timeLimit: 3600,
                                preservedUrl: 'https://storage.example.test/recordings',
                            ),
                        ),
                    ),
                ),
                new AccountDialPlanData(
                    system: ['north_america'],
                    rules: [new AccountDialPlanRuleData(
                        pattern: '^([2-9][0-9]{6})$',
                        description: 'Local',
                        prefix: '+1555',
                        preservedOptions: ['future_option' => true],
                    )],
                ),
                new AccountFormattersData([
                    new AccountFormatterRuleData(
                        field: 'request',
                        direction: 'both',
                        regex: '^sip:(.*)$',
                        prefix: 'tel:',
                        preservedOptions: ['future_option' => 'keep'],
                    ),
                ]),
                new AccountPreflowData('callflow-1'),
                new AccountMetaflowsData(
                    bindingDigit: '#',
                    digitTimeout: 2500,
                    listenOn: 'self',
                    preservedOptions: [
                        'numbers' => ['3' => ['module' => 'hangup', 'data' => [], 'children' => []]],
                        'future_option' => true,
                    ],
                ),
            ),
        );

        self::assertSame('Grid Corp', $snapshot->organizationName);
        self::assertSame('support.example.test', $snapshot->realm);
        self::assertFalse($snapshot->callWaitingEnabled);
        self::assertTrue($snapshot->doNotDisturbEnabled);
        self::assertSame('number', $snapshot->outboundPrivacy);
        self::assertSame('+15550001000', $snapshot->externalCallerIdNumber);
        self::assertSame('+15550001911', $snapshot->emergencyCallerIdNumber);
        self::assertSame('ring-2', $snapshot->externalRingtone);
        self::assertSame([
            'data' => [
                'name' => 'Support',
                'org' => 'Grid Corp',
                'timezone' => 'Asia/Manila',
                'language' => 'en-US',
                'call_waiting' => ['enabled' => false],
                'do_not_disturb' => ['enabled' => true],
                'caller_id' => [
                    'internal' => ['name' => 'Support', 'number' => '1000'],
                    'external' => ['name' => 'Grid Support', 'number' => '+15550001000'],
                    'emergency' => ['name' => 'Grid Emergency', 'number' => '+15550001911'],
                ],
                'caller_id_options' => ['outbound_privacy' => 'number', 'show_rate' => true],
                'ringtones' => ['internal' => 'ring-1', 'external' => 'ring-2'],
                'call_restriction' => ['international' => ['action' => 'deny']],
                'call_recording' => [
                    'account' => [
                        'any' => [
                            'offnet' => [
                                'enabled' => true,
                                'format' => 'wav',
                                'record_min_sec' => 5,
                                'record_on_answer' => true,
                                'record_on_bridge' => false,
                                'record_sample_rate' => 16000,
                                'time_limit' => 3600,
                                'url' => 'https://storage.example.test/recordings',
                            ],
                        ],
                    ],
                ],
                'dial_plan' => [
                    'system' => ['north_america'],
                    '^([2-9][0-9]{6})$' => [
                        'future_option' => true,
                        'description' => 'Local',
                        'prefix' => '+1555',
                    ],
                ],
                'formatters' => [
                    'request' => [[
                        'future_option' => 'keep',
                        'direction' => 'both',
                        'prefix' => 'tel:',
                        'regex' => '^sip:(.*)$',
                    ]],
                ],
                'preflow' => ['always' => 'callflow-1'],
                'metaflows' => [
                    'numbers' => ['3' => ['module' => 'hangup', 'data' => [], 'children' => []]],
                    'future_option' => true,
                    'binding_digit' => '#',
                    'digit_timeout' => 2500,
                    'listen_on' => 'self',
                ],
            ],
        ], json_decode((string) $history[0]['request']->getBody(), true));
    }

    public function test_it_updates_account_enabled_state_as_a_separate_command(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->response(['data' => ['id' => 'account-1', 'name' => 'Support', 'enabled' => false]]),
        ]));
        $stack->push(Middleware::history($history));
        $client = new AccountResourceClient(new SwitchClient(
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
        ));

        $snapshot = $client->updateEnabled('account-1', new AccountEnabledWriteData(false));

        self::assertFalse($snapshot->enabled);
        self::assertSame(['data' => ['enabled' => false]], json_decode((string) $history[0]['request']->getBody(), true));
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
