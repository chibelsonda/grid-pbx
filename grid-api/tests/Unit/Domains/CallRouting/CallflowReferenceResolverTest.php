<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Models\SwitchQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CallflowReferenceResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_safe_switch_action_variants_for_node_labels(): void
    {
        $account = SwitchAccount::factory()->create();
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'call_forward',
            'data' => ['action' => 'activate', 'number' => '+15551234567'],
            'children' => [
                '_' => [
                    'module' => 'voicemail',
                    'data' => ['action' => 'check'],
                    'children' => [],
                ],
            ],
        ]);

        $this->assertSame(['action' => 'activate', 'skip_module' => false], $flow['settings']);
        $this->assertSame(
            ['action' => 'check', 'skip_module' => false],
            $flow['children']['_']['settings'],
        );
        $this->assertArrayNotHasKey('number', $flow['settings']);
    }

    #[Test]
    public function it_exposes_only_safe_acdc_agent_state_without_raw_runtime_fields(): void
    {
        $account = SwitchAccount::factory()->create();
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'acdc_agent',
            'data' => [
                'action' => 'paused',
                'presence_id' => 'raw-presence-id',
                'presence_state' => 'red_solid',
                'timeout' => 999999,
                'skip_module' => true,
                'server_owned' => ['preserve' => true],
            ],
            'children' => [],
        ]);

        $this->assertSame(['action' => 'paused', 'skip_module' => true], $flow['settings']);
        $this->assertSame('not_applicable', $flow['reference_status']);
        $this->assertNull($flow['target']);
    }

    #[Test]
    public function it_exposes_no_high_risk_private_configuration(): void
    {
        $account = SwitchAccount::factory()->create();
        $privateData = [
            'pivot' => [
                'voice_url' => 'https://example.test/voice',
                'cdr_url' => 'https://example.test/cdr',
                'req_format' => 'twiml',
                'debug' => true,
                'server_owned' => ['preserve' => true],
            ],
            'disa' => [
                'pin' => 'private-pin',
                'retries' => 99,
                'enforce_call_restriction' => false,
                'use_account_caller_id' => false,
                'server_owned' => ['preserve' => true],
            ],
            'offnet' => [
                'to_did' => '+15551234567',
                'caller_id_type' => 'emergency',
                'custom_sip_headers' => ['X-Private' => 'secret'],
                'server_owned' => ['preserve' => true],
            ],
            'resources' => [
                'hunt_account_id' => 'raw-switch-account-id',
                'outbound_flags' => ['private-carrier'],
                'resource_type' => 'private-resource-type',
                'server_owned' => ['preserve' => true],
            ],
            'webhook' => [
                'uri' => 'https://callback.example.test/private',
                'custom_data' => ['private_token' => 'secret'],
                'retries' => 5,
                'server_owned' => ['preserve' => true],
            ],
            'dynamic_cid' => [
                'action' => 'list',
                'id' => 'raw-switch-list-id',
                'caller_id' => ['name' => 'Private', 'number' => '5555550100'],
                'enforce_call_restriction' => false,
                'permit_custom_callflow' => true,
                'server_owned' => ['preserve' => true],
            ],
        ];

        foreach ($privateData as $module => $data) {
            $flow = app(CallflowReferenceResolver::class)->resolve($account, [
                'module' => $module,
                'data' => $data,
                'children' => [],
            ]);

            $this->assertNull($flow['settings']);
            $this->assertSame('not_applicable', $flow['reference_status']);
            $this->assertNull($flow['target']);
        }
    }

    #[Test]
    public function it_exposes_only_skip_state_for_eavesdrop_nodes(): void
    {
        $account = SwitchAccount::factory()->create();

        foreach (['eavesdrop', 'eavesdrop_feature'] as $module) {
            $flow = app(CallflowReferenceResolver::class)->resolve($account, [
                'module' => $module,
                'data' => [
                    'approved_device_id' => 'raw-approved-device-id',
                    'approved_group_id' => 'raw-approved-group-id',
                    'approved_user_id' => 'raw-approved-user-id',
                    'device_id' => 'raw-target-device-id',
                    'user_id' => 'raw-target-user-id',
                    'group_id' => 'raw-target-group-id',
                    'skip_module' => true,
                    'server_owned' => ['preserve' => true],
                ],
                'children' => [],
            ]);

            $this->assertSame(['skip_module' => true], $flow['settings']);
            $this->assertSame('not_applicable', $flow['reference_status']);
            $this->assertNull($flow['target']);
        }
    }

    #[Test]
    public function it_exposes_only_resource_free_hotdesk_settings(): void
    {
        $account = SwitchAccount::factory()->create();
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'hotdesk',
            'data' => [
                'action' => 'toggle',
                'skip_module' => true,
                'id' => 'raw-user-id',
                'interdigit_timeout' => 2750,
                'server_owned' => ['preserve' => true],
            ],
            'children' => [],
        ]);

        $this->assertSame(['action' => 'toggle', 'skip_module' => true], $flow['settings']);
        $this->assertSame('not_applicable', $flow['reference_status']);
        $this->assertNull($flow['target']);
    }

    #[Test]
    public function it_exposes_only_resource_free_do_not_disturb_settings(): void
    {
        $account = SwitchAccount::factory()->create();
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'do_not_disturb',
            'data' => [
                'action' => 'toggle',
                'skip_module' => true,
                'id' => 'raw-device-id',
                'server_owned' => ['preserve' => true],
            ],
            'children' => [],
        ]);

        $this->assertSame(['action' => 'toggle', 'skip_module' => true], $flow['settings']);
        $this->assertSame('not_applicable', $flow['reference_status']);
        $this->assertNull($flow['target']);
    }

    #[Test]
    public function it_distinguishes_conference_service_nodes_without_exposing_switch_ids(): void
    {
        $account = SwitchAccount::factory()->create();
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'conference',
            'data' => ['skip_module' => true, 'private_prompt_id' => 'raw-prompt-id'],
            'children' => [],
        ]);

        $this->assertSame(['service_mode' => true, 'skip_module' => true], $flow['settings']);
        $this->assertSame('not_applicable', $flow['reference_status']);
        $this->assertNull($flow['target']);
    }

    #[Test]
    public function it_distinguishes_check_voicemail_without_exposing_auto_login_settings(): void
    {
        $account = SwitchAccount::factory()->create();
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'voicemail',
            'data' => [
                'action' => 'check',
                'skip_module' => true,
                'single_mailbox_login' => true,
                'callerid_match_login' => true,
                'private_prompt_id' => 'raw-prompt-id',
            ],
            'children' => [],
        ]);

        $this->assertSame(['action' => 'check', 'skip_module' => true], $flow['settings']);
        $this->assertSame('not_applicable', $flow['reference_status']);
        $this->assertNull($flow['target']);
    }

    #[Test]
    public function it_treats_time_of_day_operations_as_inline_actions_not_missing_destinations(): void
    {
        $account = SwitchAccount::factory()->create();
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'temporal_route',
            'data' => ['action' => 'reset', 'rules' => [], 'skip_module' => true],
            'children' => [],
        ]);

        $this->assertSame('not_applicable', $flow['reference_status']);
        $this->assertSame(
            ['action' => 'reset', 'rules' => [], 'skip_module' => true],
            $flow['settings'],
        );
    }

    #[Test]
    public function it_resolves_ring_group_toggle_callflows_to_public_identifiers(): void
    {
        $account = SwitchAccount::factory()->create();
        $ringGroupCallflow = SwitchCallflow::factory()->for($account)->create([
            'switch_resource_id' => 'switch-ring-group-callflow',
            'name' => 'Support ring group',
            'modules' => ['ring_group', 'voicemail'],
        ]);
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'ring_group_toggle',
            'data' => [
                'action' => 'login',
                'callflow_id' => 'switch-ring-group-callflow',
                'skip_module' => false,
            ],
            'children' => [],
        ]);

        $this->assertSame('resolved', $flow['reference_status']);
        $this->assertSame((string) $ringGroupCallflow->id, $flow['target']['id']);
        $this->assertSame((string) $ringGroupCallflow->id, $flow['settings']['callflow_id']);
        $this->assertTrue($flow['settings']['supported_configuration']);
    }

    #[Test]
    public function it_resolves_acdc_queue_actions_without_exposing_the_raw_queue_id(): void
    {
        $account = SwitchAccount::factory()->create();
        $queue = SwitchQueue::factory()->for($account)->create([
            'switch_resource_id' => 'switch-support-queue',
            'name' => 'Support',
        ]);
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'acdc_queue',
            'data' => [
                'action' => 'login',
                'id' => 'switch-support-queue',
                'skip_module' => true,
                'server_owned' => ['preserve' => true],
            ],
            'children' => [],
        ]);

        $this->assertSame('resolved', $flow['reference_status']);
        $this->assertSame((string) $queue->id, $flow['target']['id']);
        $this->assertSame([
            'action' => 'login',
            'queue_id' => (string) $queue->id,
            'queue_label' => 'Support',
            'supported_configuration' => true,
            'skip_module' => true,
        ], $flow['settings']);
        $this->assertStringNotContainsString('switch-support-queue', json_encode($flow, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function it_locks_ambiguous_group_pickup_targets_without_exposing_private_restrictions(): void
    {
        $account = SwitchAccount::factory()->create();
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'group_pickup',
            'data' => [
                'device_id' => 'switch-device',
                'group_id' => 'switch-group',
                'approved_user_id' => 'private-approved-user',
                'skip_module' => true,
            ],
            'children' => [],
        ]);

        $this->assertSame('unresolved', $flow['reference_status']);
        $this->assertNull($flow['target']);
        $this->assertSame([
            'supported_target' => false,
            'target_type' => null,
            'target_id' => null,
            'target_label' => null,
            'reference_status' => 'unresolved',
            'skip_module' => true,
        ], $flow['settings']);
        $this->assertStringNotContainsString('private-approved-user', json_encode($flow));
    }

    #[Test]
    public function it_resolves_receive_fax_owner_and_hides_unknown_media_settings(): void
    {
        $account = SwitchAccount::factory()->create();
        $owner = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-fax-owner',
            'display_name' => 'Fax Reception',
        ]);
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'receive_fax',
            'data' => [
                'owner_id' => 'switch-fax-owner',
                'media' => ['fax_option' => 'auto', 'private_transport' => 'secret'],
                'skip_module' => true,
            ],
            'children' => [],
        ]);

        $this->assertSame('resolved', $flow['reference_status']);
        $this->assertSame((string) $owner->id, $flow['target']['id']);
        $this->assertSame([
            'supported_configuration' => true,
            'owner_id' => (string) $owner->id,
            'owner_label' => 'Fax Reception',
            'reference_status' => 'resolved',
            'fax_option' => 'auto',
            'skip_module' => true,
        ], $flow['settings']);
        $this->assertStringNotContainsString('secret', json_encode($flow));
    }

    #[Test]
    public function it_resolves_safe_page_group_devices_without_exposing_switch_endpoint_data(): void
    {
        $account = SwitchAccount::factory()->create();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-page-device',
            'name' => 'Warehouse speaker',
        ]);
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'page_group',
            'data' => [
                'audio' => 'two-way',
                'timeout' => 5,
                'endpoints' => [[
                    'endpoint_type' => 'device',
                    'id' => 'switch-page-device',
                    'delay' => 0,
                    'timeout' => 20,
                    'server_owned' => 'secret',
                ]],
                'skip_module' => true,
            ],
            'children' => [],
        ]);

        $this->assertSame('resolved', $flow['reference_status']);
        $this->assertNull($flow['target']);
        $this->assertSame([
            'supported_configuration' => true,
            'audio' => 'two-way',
            'device_ids' => [(string) $device->id],
            'reference_status' => 'resolved',
            'skip_module' => true,
        ], $flow['settings']);
        $this->assertStringNotContainsString('switch-page-device', json_encode($flow));
        $this->assertStringNotContainsString('secret', json_encode($flow));

        $unsafe = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'page_group',
            'data' => [
                'audio' => 'one-way',
                'barge_calls' => true,
                'endpoints' => [['endpoint_type' => 'device', 'id' => 'switch-page-device']],
            ],
            'children' => [],
        ]);

        $this->assertFalse($unsafe['settings']['supported_configuration']);
        $this->assertSame([], $unsafe['settings']['device_ids']);
    }

    #[Test]
    public function it_resolves_safe_ring_group_endpoints_without_exposing_switch_endpoint_data(): void
    {
        $account = SwitchAccount::factory()->create();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-ring-group-device',
            'name' => 'Reception phone',
        ]);
        $ringback = SwitchMedia::factory()->for($account)->create([
            'switch_resource_id' => 'switch-ringback-media',
            'name' => 'Support ringback',
            'content_type' => 'audio/mpeg',
            'streamable' => true,
        ]);
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'ring_group',
            'data' => [
                'strategy' => 'simultaneous',
                'timeout' => 25,
                'repeats' => 2,
                'ringback' => 'switch-ringback-media',
                'ringtones' => [
                    'internal' => 'internal-alert',
                    'external' => 'external-alert',
                    'server_owned' => 'secret-ringtone',
                ],
                'ignore_forward' => false,
                'fail_on_single_reject' => true,
                'endpoints' => [[
                    'endpoint_type' => 'device',
                    'id' => 'switch-ring-group-device',
                    'delay' => 5,
                    'timeout' => 20,
                    'server_owned' => 'secret',
                ]],
                'skip_module' => true,
            ],
            'children' => [],
        ]);

        $this->assertSame('resolved', $flow['reference_status']);
        $this->assertNull($flow['target']);
        $this->assertSame([
            'supported_configuration' => true,
            'strategy' => 'simultaneous',
            'endpoints' => [[
                'device_id' => (string) $device->id,
                'delay' => 5,
                'timeout' => 20,
            ]],
            'repeats' => 2,
            'ignore_forward' => false,
            'fail_on_single_reject' => true,
            'ringback_media_id' => (string) $ringback->id,
            'ringtone_internal' => 'internal-alert',
            'ringtone_external' => 'external-alert',
            'reference_status' => 'resolved',
            'skip_module' => true,
        ], $flow['settings']);
        $this->assertStringNotContainsString('switch-ring-group-device', json_encode($flow));
        $this->assertStringNotContainsString('switch-ringback-media', json_encode($flow));
        $this->assertStringNotContainsString('secret-ringtone', json_encode($flow));
        $this->assertStringNotContainsString('secret', json_encode($flow));

        $weighted = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'ring_group',
            'data' => [
                'strategy' => 'weighted_random',
                'timeout' => 20,
                'endpoints' => [[
                    'endpoint_type' => 'device',
                    'id' => 'switch-ring-group-device',
                    'delay' => 0,
                    'timeout' => 20,
                    'weight' => 50,
                ]],
            ],
            'children' => [],
        ]);

        $this->assertSame('resolved', $weighted['reference_status']);
        $this->assertSame([
            'supported_configuration' => true,
            'strategy' => 'weighted_random',
            'endpoints' => [[
                'device_id' => (string) $device->id,
                'delay' => 0,
                'timeout' => 20,
                'weight' => 50,
            ]],
            'repeats' => 1,
            'ignore_forward' => true,
            'fail_on_single_reject' => false,
            'ringback_media_id' => null,
            'ringtone_internal' => null,
            'ringtone_external' => null,
            'reference_status' => 'resolved',
            'skip_module' => false,
        ], $weighted['settings']);

        $unsafe = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'ring_group',
            'data' => [
                'strategy' => 'weighted_random',
                'timeout' => 20,
                'endpoints' => [[
                    'endpoint_type' => 'device',
                    'id' => 'switch-ring-group-device',
                    'delay' => 0,
                    'timeout' => 20,
                ]],
            ],
            'children' => [],
        ]);

        $this->assertFalse($unsafe['settings']['supported_configuration']);
        $this->assertSame([], $unsafe['settings']['endpoints']);

        $malformedFlag = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'ring_group',
            'data' => [
                'strategy' => 'simultaneous',
                'timeout' => 20,
                'ignore_forward' => 'true',
                'endpoints' => [[
                    'endpoint_type' => 'device',
                    'id' => 'switch-ring-group-device',
                    'delay' => 0,
                    'timeout' => 20,
                ]],
            ],
            'children' => [],
        ]);

        $this->assertFalse($malformedFlag['settings']['supported_configuration']);
        $this->assertNull($malformedFlag['settings']['ignore_forward']);

        foreach ([
            ['ringback' => 'https://metadata.invalid/ringback'],
            ['ringback' => 123],
            ['ringtones' => ['internal' => "safe\r\nX-Injected: true"]],
        ] as $unsafeMedia) {
            $unsupportedMedia = app(CallflowReferenceResolver::class)->resolve($account, [
                'module' => 'ring_group',
                'data' => [
                    'strategy' => 'simultaneous',
                    'timeout' => 20,
                    'endpoints' => [[
                        'endpoint_type' => 'device',
                        'id' => 'switch-ring-group-device',
                        'delay' => 0,
                        'timeout' => 20,
                    ]],
                    ...$unsafeMedia,
                ],
                'children' => [],
            ]);

            $this->assertFalse($unsupportedMedia['settings']['supported_configuration']);
            $this->assertNull($unsupportedMedia['settings']['ringback_media_id']);
            $this->assertStringNotContainsString('metadata.invalid', json_encode($unsupportedMedia));
            $this->assertStringNotContainsString('X-Injected', json_encode($unsupportedMedia));
        }

        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-expanded-user',
        ]);
        $group = SwitchGroup::factory()->for($account)->create([
            'switch_resource_id' => 'switch-expanded-group',
        ]);

        foreach ([
            [
                'endpoint_type' => 'user',
                'public_key' => 'extension_id',
                'public_id' => (string) $extension->id,
                'raw_id' => $extension->switch_resource_id,
            ],
            [
                'endpoint_type' => 'group',
                'public_key' => 'group_id',
                'public_id' => (string) $group->id,
                'raw_id' => $group->switch_resource_id,
            ],
        ] as $expandedEndpoint) {
            $resolved = app(CallflowReferenceResolver::class)->resolve($account, [
                'module' => 'ring_group',
                'data' => [
                    'strategy' => 'simultaneous',
                    'timeout' => 20,
                    'endpoints' => [[
                        'endpoint_type' => $expandedEndpoint['endpoint_type'],
                        'id' => $expandedEndpoint['raw_id'],
                        'delay' => 0,
                        'timeout' => 20,
                    ]],
                ],
                'children' => [],
            ]);

            $this->assertTrue($resolved['settings']['supported_configuration']);
            $this->assertSame($expandedEndpoint['public_id'], $resolved['settings']['endpoints'][0][$expandedEndpoint['public_key']]);
            $this->assertStringNotContainsString($expandedEndpoint['raw_id'], json_encode($resolved));
        }
    }
}
