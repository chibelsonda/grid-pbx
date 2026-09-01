<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Services\CallflowInlineNodeDataValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CallflowInlineNodeDataValidatorTest extends TestCase
{
    #[Test]
    public function it_accepts_only_schema_backed_operational_action_payloads(): void
    {
        $validator = app(CallflowInlineNodeDataValidator::class);

        $this->assertSame(
            ['action' => 'disable', 'rules' => [], 'skip_module' => false],
            $validator->validate('temporal_route', [
                'action' => 'disable',
                'rules' => [],
                'skip_module' => false,
            ]),
        );
        $this->assertSame(
            ['action' => 'logout', 'skip_module' => false],
            $validator->validate('hotdesk', ['action' => 'logout', 'skip_module' => false]),
        );
        $this->assertSame(
            [
                'action' => 'login',
                'queue_id' => '11111111-1111-4111-8111-111111111111',
                'skip_module' => false,
            ],
            $validator->validate('acdc_queue', [
                'action' => 'login',
                'queue_id' => '11111111-1111-4111-8111-111111111111',
                'skip_module' => false,
            ]),
        );

        foreach ([
            ['action' => 'toggle', 'queue_id' => '11111111-1111-4111-8111-111111111111', 'skip_module' => false],
            ['action' => 'login', 'queue_id' => 'raw-switch-queue', 'skip_module' => false],
            ['action' => 'login', 'queue_id' => '11111111-1111-4111-8111-111111111111', 'id' => 'raw-switch-queue', 'skip_module' => false],
        ] as $data) {
            try {
                $validator->validate('acdc_queue', $data);
                $this->fail('ACDC Queue must reject unsupported actions and raw Queue IDs.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_accepts_only_schema_backed_call_forward_payloads(): void
    {
        $validator = app(CallflowInlineNodeDataValidator::class);

        foreach (['activate', 'deactivate', 'update'] as $action) {
            $this->assertSame(
                ['action' => $action, 'skip_module' => false],
                $validator->validate('call_forward', [
                    'action' => $action,
                    'skip_module' => false,
                ]),
            );
        }

        foreach ([
            ['action' => 'toggle', 'skip_module' => false],
            ['action' => 'activate', 'number' => '+15551234567', 'skip_module' => false],
            ['action' => 'activate', 'id' => 'raw-user-id', 'skip_module' => false],
        ] as $data) {
            try {
                $validator->validate('call_forward', $data);
                $this->fail('Call Forwarding must reject unsupported actions and private fields.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_accepts_only_static_dynamic_cid_with_a_public_phone_number_reference(): void
    {
        $validator = app(CallflowInlineNodeDataValidator::class);
        $phoneNumberId = '11111111-1111-4111-8111-111111111111';
        $settings = [
            'action' => 'static',
            'phone_number_id' => $phoneNumberId,
            'caller_id_name' => 'Support',
            'skip_module' => false,
        ];

        $this->assertSame($settings, $validator->validate('dynamic_cid', $settings));

        foreach ([
            [...$settings, 'action' => 'manual'],
            [...$settings, 'phone_number_id' => '+15551234567'],
            [...$settings, 'caller_id_number' => '+15551234567'],
        ] as $data) {
            try {
                $validator->validate('dynamic_cid', $data);
                $this->fail('Dynamic CID must reject manual mode, raw numbers, and private fields.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_accepts_only_a_public_disa_policy_reference(): void
    {
        $validator = app(CallflowInlineNodeDataValidator::class);
        $policyId = '11111111-1111-4111-8111-111111111111';

        $this->assertSame(
            ['access_policy_id' => $policyId, 'skip_module' => false],
            $validator->validate('disa', [
                'access_policy_id' => $policyId,
                'skip_module' => false,
            ]),
        );

        foreach ([
            ['access_policy_id' => 'raw-switch-policy', 'skip_module' => false],
            ['access_policy_id' => $policyId, 'pin' => '82736491', 'skip_module' => false],
            ['access_policy_id' => $policyId, 'enforce_call_restriction' => false, 'skip_module' => false],
        ] as $data) {
            try {
                $validator->validate('disa', $data);
                $this->fail('DISA must reject raw credentials and browser-controlled security policy.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_accepts_only_the_resource_free_conference_service_discriminator(): void
    {
        $validator = app(CallflowInlineNodeDataValidator::class);

        $this->assertSame(
            ['service_mode' => true, 'skip_module' => false],
            $validator->validate('conference', ['service_mode' => true, 'skip_module' => false]),
        );

        foreach ([
            ['service_mode' => false, 'skip_module' => false],
            ['service_mode' => true, 'skip_module' => false, 'id' => 'raw-conference-id'],
        ] as $data) {
            try {
                $validator->validate('conference', $data);
                $this->fail('Conference Service must reject non-service and resource-bearing payloads.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_accepts_only_resource_free_check_voicemail_settings(): void
    {
        $validator = app(CallflowInlineNodeDataValidator::class);

        $this->assertSame(
            ['action' => 'check', 'skip_module' => false],
            $validator->validate('voicemail', ['action' => 'check', 'skip_module' => false]),
        );

        foreach ([
            ['action' => 'compose', 'skip_module' => false],
            ['action' => 'check', 'skip_module' => false, 'id' => 'raw-voicemail-id'],
            ['action' => 'check', 'skip_module' => false, 'single_mailbox_login' => true],
        ] as $data) {
            try {
                $validator->validate('voicemail', $data);
                $this->fail('Check Voicemail must reject compose, resource, and auto-login payloads.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_accepts_only_bounded_public_device_page_groups(): void
    {
        $validator = app(CallflowInlineNodeDataValidator::class);
        $deviceId = '11111111-1111-4111-8111-111111111111';

        $this->assertSame(
            ['audio' => 'two-way', 'device_ids' => [$deviceId], 'skip_module' => false],
            $validator->validate('page_group', [
                'audio' => 'two-way',
                'device_ids' => [$deviceId],
                'skip_module' => false,
            ]),
        );

        foreach ([
            ['audio' => 'one-way', 'device_ids' => [], 'skip_module' => false],
            ['audio' => 'barge', 'device_ids' => [$deviceId], 'skip_module' => false],
            ['audio' => 'one-way', 'device_ids' => [$deviceId, $deviceId], 'skip_module' => false],
            ['audio' => 'one-way', 'device_ids' => ['raw-switch-device'], 'skip_module' => false],
        ] as $data) {
            try {
                $validator->validate('page_group', $data);
                $this->fail('Page Group must reject unsafe or non-public endpoint selections.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_accepts_only_bounded_public_ring_group_endpoints(): void
    {
        $validator = app(CallflowInlineNodeDataValidator::class);
        $deviceId = '11111111-1111-4111-8111-111111111111';
        $settings = [
            'strategy' => 'simultaneous',
            'endpoints' => [[
                'device_id' => $deviceId,
                'delay' => 5,
                'timeout' => 20,
            ]],
            'repeats' => 2,
            'ignore_forward' => true,
            'fail_on_single_reject' => false,
            'ringback_media_id' => null,
            'ringtone_internal' => null,
            'ringtone_external' => null,
            'skip_module' => false,
        ];

        $this->assertSame($settings, $validator->validate('ring_group', $settings));
        $weighted = [
            ...$settings,
            'strategy' => 'weighted_random',
            'endpoints' => [[
                'device_id' => $deviceId,
                'delay' => 0,
                'timeout' => 20,
                'weight' => 75,
            ]],
        ];

        $this->assertSame($weighted, $validator->validate('ring_group', $weighted));
        $extension = [
            ...$settings,
            'endpoints' => [[
                'extension_id' => '22222222-2222-4222-8222-222222222222',
                'delay' => 5,
                'timeout' => 20,
            ]],
        ];
        $group = [
            ...$settings,
            'endpoints' => [[
                'group_id' => '33333333-3333-4333-8333-333333333333',
                'delay' => 5,
                'timeout' => 20,
            ]],
        ];

        $this->assertSame($extension, $validator->validate('ring_group', $extension));
        $this->assertSame($group, $validator->validate('ring_group', $group));

        foreach ([
            [...$weighted, 'endpoints' => [[
                'device_id' => $deviceId,
                'delay' => 0,
                'timeout' => 20,
            ]]],
            [...$weighted, 'endpoints' => [[
                'device_id' => $deviceId,
                'delay' => 1,
                'timeout' => 20,
                'weight' => 75,
            ]]],
            [...$settings, 'endpoints' => [[
                'device_id' => $deviceId,
                'delay' => 5,
                'timeout' => 20,
                'weight' => 75,
            ]]],
            [...$settings, 'endpoints' => []],
            [...$settings, 'endpoints' => [[
                'device_id' => 'raw-switch-device',
                'delay' => 5,
                'timeout' => 20,
            ]]],
            [...$settings, 'endpoints' => [[
                'device_id' => $deviceId,
                'group_id' => '33333333-3333-4333-8333-333333333333',
                'delay' => 5,
                'timeout' => 20,
            ]]],
            [...$settings, 'strategy' => 'single', 'endpoints' => [[
                'device_id' => $deviceId,
                'delay' => 1,
                'timeout' => 20,
            ]]],
            [...$settings, 'strategy' => 'single', 'endpoints' => [[
                'device_id' => $deviceId,
                'delay' => 0,
                'timeout' => 50,
            ], [
                'device_id' => '22222222-2222-4222-8222-222222222222',
                'delay' => 0,
                'timeout' => 50,
            ], [
                'device_id' => '33333333-3333-4333-8333-333333333333',
                'delay' => 0,
                'timeout' => 50,
            ]]],
            [...$settings, 'endpoints' => [[
                'device_id' => $deviceId,
                'delay' => 60,
                'timeout' => 60,
            ], [
                'device_id' => '22222222-2222-4222-8222-222222222222',
                'delay' => 60,
                'timeout' => 61,
            ]]],
            [...$settings, 'repeats' => 4],
            [...$settings, 'ignore_forward' => 'true'],
            [...$settings, 'fail_on_single_reject' => 1],
            [...$settings, 'ringback_media_id' => 'raw-switch-media'],
            [...$settings, 'ringtone_internal' => "safe\r\nX-Injected: true"],
            [...$settings, 'ringtone_external' => str_repeat('x', 257)],
        ] as $data) {
            try {
                $validator->validate('ring_group', $data);
                $this->fail('Ring Group must reject unsafe or non-public endpoint selections.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }
}
