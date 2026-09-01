<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Users\Dto\CallerId\UserCallerIdData;
use GridPbx\Switch\Domains\Users\Dto\CallerId\UserCallerIdScopeData;
use GridPbx\Switch\Domains\Users\Dto\CallForwarding\UserCallForwardData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserCallRecordingData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserRecordingParametersData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserRecordingRulesData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserRecordingSourceData;
use GridPbx\Switch\Domains\Users\Dto\CallRestrictions\UserCallRestrictionsData;
use GridPbx\Switch\Domains\Users\Dto\Credentials\UserCredentialsData;
use GridPbx\Switch\Domains\Users\Dto\Hotdesk\UserHotdeskData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserMediaData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserMusicOnHoldData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserPronouncedNameData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserRingtonesData;
use GridPbx\Switch\Domains\Users\Dto\Metaflows\UserMetaflowsData;
use GridPbx\Switch\Domains\Users\Dto\Profile\UserProfileAddressData;
use GridPbx\Switch\Domains\Users\Dto\Profile\UserProfileData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserDialPlanData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserDialPlanRuleData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserFormatterRuleData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserFormattersData;
use GridPbx\Switch\Domains\Users\Dto\UserAdvancedData;
use GridPbx\Switch\Domains\Users\Dto\UserWriteData;
use GridPbx\Switch\Domains\Users\UserResourceClient;
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

final class UserResourceClientTest extends TestCase
{
    public function test_empty_call_restrictions_are_serialized_as_a_switch_object(): void
    {
        $restrictions = new UserCallRestrictionsData([]);

        self::assertSame('{}', json_encode($restrictions->toSwitchData(), JSON_THROW_ON_ERROR));
    }

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_maps_schema_backed_user_calling_settings_and_preserved_metadata(): void
    {
        $parameters = new UserRecordingParametersData(
            enabled: true,
            format: 'wav',
            minimumSeconds: 2,
            recordOnAnswer: true,
            recordOnBridge: false,
            sampleRate: 16000,
            timeLimit: 300,
            preservedOptions: ['url' => 'https://recordings.example.test/user'],
        );
        $source = new UserRecordingSourceData($parameters, $parameters, $parameters);
        $rules = new UserRecordingRulesData($source, $source, $source);
        $data = (new UserAdvancedData(
            callerId: new UserCallerIdData(
                internal: new UserCallerIdScopeData('Alice', '1001'),
                external: new UserCallerIdScopeData('Support', '+15550001001'),
                emergency: new UserCallerIdScopeData('Alice', '+15550001911'),
                preservedOptions: ['asserted' => ['realm' => 'pbx.example.test']],
            ),
            callForward: new UserCallForwardData(
                enabled: true,
                number: '+15550001002',
                requireKeypress: false,
                preservedOptions: ['future_option' => true],
            ),
            callRestrictions: new UserCallRestrictionsData(
                actions: ['international' => 'deny'],
                preservedOptions: ['international' => ['future_option' => true]],
            ),
            callRecording: new UserCallRecordingData($rules),
            media: new UserMediaData(
                audioCodecs: ['OPUS', 'PCMU'],
                videoCodecs: ['H264'],
                bypassMedia: 'auto',
                enforceEncryption: true,
                encryptionMethods: ['srtp'],
                faxOption: false,
                ignoreEarlyMedia: true,
                progressTimeout: 30,
                preservedOptions: [
                    'audio' => ['future_audio_option' => true],
                    'future_media_option' => 'preserved',
                ],
            ),
            musicOnHold: new UserMusicOnHoldData(
                mediaId: 'switch-media-1',
                preservedOptions: ['future_moh_option' => true],
            ),
            ringtones: new UserRingtonesData(
                internal: 'Internal-ring',
                external: 'External-ring',
                preservedOptions: ['future_ringtone_option' => true],
            ),
            dialPlan: new UserDialPlanData(
                system: ['north_america'],
                rules: [new UserDialPlanRuleData(
                    pattern: '^9(.*)$',
                    prefix: '+1',
                    preservedOptions: ['future_rule_option' => true],
                )],
            ),
            formatters: new UserFormattersData([
                new UserFormatterRuleData(
                    field: 'request',
                    direction: 'outbound',
                    regex: '^(.*)$',
                    preservedOptions: ['future_formatter_option' => true],
                ),
            ]),
            profile: new UserProfileData(
                addresses: [new UserProfileAddressData('100 Main Street', ['work'])],
                nicknames: ['Ops'],
                title: 'Support Engineer',
                preservedOptions: ['future_profile_option' => true],
            ),
            pronouncedName: new UserPronouncedNameData(
                mediaId: 'switch-media-name',
                preservedOptions: ['future_name_option' => true],
            ),
            preservedOptions: [
                'verified' => true,
                'flags' => ['externally-managed'],
            ],
        ))->toSwitchData();

        self::assertSame('pbx.example.test', $data['caller_id']['asserted']['realm']);
        self::assertSame('+15550001002', $data['call_forward']['number']);
        self::assertFalse($data['call_forward']['require_keypress']);
        self::assertTrue($data['call_forward']['future_option']);
        self::assertSame('deny', $data['call_restriction']['international']['action']);
        self::assertTrue($data['call_restriction']['international']['future_option']);
        self::assertSame(
            'https://recordings.example.test/user',
            $data['call_recording']['outbound']['offnet']['url'],
        );
        self::assertSame(['OPUS', 'PCMU'], $data['media']['audio']['codecs']);
        self::assertTrue($data['media']['audio']['future_audio_option']);
        self::assertSame('preserved', $data['media']['future_media_option']);
        self::assertSame('switch-media-1', $data['music_on_hold']['media_id']);
        self::assertTrue($data['music_on_hold']['future_moh_option']);
        self::assertSame('Internal-ring', $data['ringtones']['internal']);
        self::assertTrue($data['ringtones']['future_ringtone_option']);
        self::assertSame('+1', $data['dial_plan']['^9(.*)$']['prefix']);
        self::assertTrue($data['dial_plan']['^9(.*)$']['future_rule_option']);
        self::assertSame('outbound', $data['formatters']['request'][0]['direction']);
        self::assertTrue($data['formatters']['request'][0]['future_formatter_option']);
        self::assertSame('Support Engineer', $data['profile']['title']);
        self::assertTrue($data['profile']['future_profile_option']);
        self::assertSame('switch-media-name', $data['pronounced_name']['media_id']);
        self::assertTrue($data['pronounced_name']['future_name_option']);
        self::assertTrue($data['verified']);
        self::assertSame(['externally-managed'], $data['flags']);
    }

    public function test_it_omits_an_empty_call_forward_number_for_switch_schema_compatibility(): void
    {
        $data = (new UserCallForwardData(enabled: false))->toSwitchData();

        self::assertArrayNotHasKey('number', $data);
        self::assertFalse($data['enabled']);
    }

    public function test_it_omits_nullable_recording_parameters_for_switch_schema_compatibility(): void
    {
        $data = (new UserRecordingParametersData(
            enabled: false,
            format: 'mp3',
        ))->toSwitchData();

        self::assertArrayNotHasKey('record_min_sec', $data);
        self::assertArrayNotHasKey('record_sample_rate', $data);
        self::assertArrayNotHasKey('time_limit', $data);
    }

    public function test_it_serializes_an_empty_caller_id_scope_as_a_json_object(): void
    {
        $data = (new UserCallerIdScopeData)->toSwitchData();

        self::assertSame('{}', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function test_it_omits_an_empty_media_progress_timeout_for_switch_schema_compatibility(): void
    {
        $data = (new UserMediaData(
            audioCodecs: [],
            videoCodecs: [],
            bypassMedia: false,
            enforceEncryption: false,
            encryptionMethods: [],
            faxOption: false,
            ignoreEarlyMedia: false,
            progressTimeout: null,
        ))->toSwitchData();

        self::assertArrayNotHasKey('progress_timeout', $data);
    }

    public function test_it_serializes_empty_metaflow_maps_as_json_objects(): void
    {
        $data = (new UserMetaflowsData(
            preservedOptions: ['numbers' => [], 'patterns' => []],
        ))->toSwitchData();

        self::assertSame('{}', json_encode($data['numbers'], JSON_THROW_ON_ERROR));
        self::assertSame('{}', json_encode($data['patterns'], JSON_THROW_ON_ERROR));
    }

    public function test_it_creates_a_user_with_a_bounded_extension_payload(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Operator',
                'username' => 'alice.operator',
                'require_password_update' => true,
                'caller_id' => ['internal' => ['number' => '1001']],
            ]]),
        ]);

        $snapshot = $client->create('account-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Operator',
            extension: '1001',
            email: 'alice@example.test',
            timezone: 'America/Los_Angeles',
            advanced: new UserAdvancedData(
                language: 'en-US',
                presenceId: 'alice@pbx.example.test',
                callWaiting: false,
                doNotDisturb: true,
                excludeFromContactList: true,
                outboundPrivacy: 'name',
                metaflows: new UserMetaflowsData(
                    bindingDigit: '#',
                    digitTimeout: 2500,
                    listenOn: 'self',
                    preservedOptions: [
                        'numbers' => [
                            '4' => ['module' => 'hangup', 'data' => [], 'children' => []],
                        ],
                        'future_option' => true,
                    ],
                ),
            ),
            hotdesk: new UserHotdeskData(
                enabled: true,
                id: '1001',
                keepLoggedInElsewhere: true,
                requirePin: true,
                pin: '2468',
            ),
            credentials: new UserCredentialsData(
                username: 'alice.operator',
                password: 'correct horse battery staple',
                requirePasswordUpdate: true,
            ),
        ));

        self::assertSame('user-1', $snapshot->id);
        self::assertSame('PUT', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/users', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'data' => [
                'first_name' => 'Alice',
                'last_name' => 'Operator',
                'enabled' => true,
                'caller_id' => [
                    'internal' => [
                        'name' => 'Alice Operator',
                        'number' => '1001',
                    ],
                ],
                'presence_id' => 'alice@pbx.example.test',
                'email' => 'alice@example.test',
                'timezone' => 'America/Los_Angeles',
                'language' => 'en-US',
                'call_waiting' => ['enabled' => false],
                'do_not_disturb' => ['enabled' => true],
                'contact_list' => ['exclude' => true],
                'caller_id_options' => ['outbound_privacy' => 'name'],
                'metaflows' => [
                    'numbers' => [
                        '4' => ['module' => 'hangup', 'data' => [], 'children' => []],
                    ],
                    'future_option' => true,
                    'binding_digit' => '#',
                    'digit_timeout' => 2500,
                    'listen_on' => 'self',
                ],
                'require_password_update' => true,
                'username' => 'alice.operator',
                'password' => 'correct horse battery staple',
                'hotdesk' => [
                    'enabled' => true,
                    'keep_logged_in_elsewhere' => true,
                    'require_pin' => true,
                    'id' => '1001',
                    'pin' => '2468',
                ],
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertTrue($snapshot->requirePasswordUpdate);
    }

    public function test_it_updates_an_unchanged_login_without_resending_the_password(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'username' => 'alice.operator',
                'require_password_update' => false,
            ]]),
        ]);

        $snapshot = $client->update('account-1', 'user-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Support',
            extension: '1001',
            credentials: new UserCredentialsData(username: 'alice.operator'),
        ));
        $body = json_decode(
            (string) $this->history[0]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertCount(1, $this->history);
        self::assertSame('alice.operator', $body['data']['username']);
        self::assertArrayNotHasKey('password', $body['data']);
        self::assertFalse($snapshot->requirePasswordUpdate);
    }

    public function test_it_removes_login_credentials_without_sending_a_password(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'require_password_update' => false,
            ]]),
        ]);

        $client->update('account-1', 'user-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Support',
            extension: '1001',
            credentials: new UserCredentialsData,
        ));
        $body = json_decode(
            (string) $this->history[0]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayNotHasKey('username', $body['data']);
        self::assertArrayNotHasKey('password', $body['data']);
        self::assertFalse($body['data']['require_password_update']);
    }

    public function test_it_preserves_a_write_only_hotdesk_pin_during_an_update(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Operator',
                'hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => true, 'pin' => '2468'],
            ]]),
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => true, 'pin' => '2468'],
            ]]),
        ]);

        $snapshot = $client->update('account-1', 'user-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Support',
            extension: '1001',
            hotdesk: new UserHotdeskData(
                enabled: true,
                id: '1001',
                requirePin: true,
                preservePin: true,
            ),
        ));

        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        self::assertSame(
            '2468',
            json_decode(
                (string) $this->history[1]['request']->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR,
            )['data']['hotdesk']['pin'],
        );
        self::assertTrue($snapshot->hotdeskPinConfigured);
    }

    public function test_it_clears_a_hotdesk_pin_without_reading_the_existing_secret(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'hotdesk' => ['enabled' => true, 'id' => '1001', 'require_pin' => false],
            ]]),
        ]);

        $snapshot = $client->update('account-1', 'user-1', new UserWriteData(
            firstName: 'Alice',
            lastName: 'Support',
            extension: '1001',
            hotdesk: new UserHotdeskData(enabled: true, id: '1001'),
        ));

        $body = json_decode(
            (string) $this->history[0]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertCount(1, $this->history);
        self::assertArrayNotHasKey('pin', $body['data']['hotdesk']);
        self::assertFalse($snapshot->hotdeskPinConfigured);
    }

    public function test_it_updates_and_deletes_a_user(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'user-1',
                'first_name' => 'Alice',
                'last_name' => 'Support',
                'caller_id' => ['internal' => ['number' => '1001']],
            ]]),
            $this->response(['data' => []]),
        ]);

        $client->update('account-1', 'user-1', new UserWriteData('Alice', 'Support', '1001'));
        $client->delete('account-1', 'user-1');

        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/users/user-1', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('DELETE', $this->history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/users/user-1', $this->history[1]['request']->getUri()->getPath());
    }

    public function test_it_rejects_an_update_response_for_a_different_user(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'other-user',
                'first_name' => 'Wrong',
                'last_name' => 'User',
            ]]),
        ]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('does not match');

        $client->update('account-1', 'user-1', new UserWriteData('Alice', 'Support', '1001'));
    }

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses): UserResourceClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $http = new Client(['handler' => $stack]);
        $switch = new SwitchClient(
            $http,
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            $this->tokenProvider(),
        );

        return new UserResourceClient($switch);
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }

    private function tokenProvider(): TokenProvider
    {
        return new class implements TokenProvider
        {
            public function token(): string
            {
                return 'test-token';
            }

            public function invalidate(): void {}
        };
    }
}
