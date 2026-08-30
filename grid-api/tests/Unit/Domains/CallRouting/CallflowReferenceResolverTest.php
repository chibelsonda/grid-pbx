<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
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
        $this->assertSame(['action' => 'check'], $flow['children']['_']['settings']);
        $this->assertArrayNotHasKey('number', $flow['settings']);
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
    public function it_resolves_safe_ring_group_devices_without_exposing_switch_endpoint_data(): void
    {
        $account = SwitchAccount::factory()->create();
        $device = SwitchDevice::factory()->for($account)->create([
            'switch_resource_id' => 'switch-ring-group-device',
            'name' => 'Reception phone',
        ]);
        $flow = app(CallflowReferenceResolver::class)->resolve($account, [
            'module' => 'ring_group',
            'data' => [
                'strategy' => 'simultaneous',
                'timeout' => 25,
                'repeats' => 2,
                'ringback' => 'private-media-id',
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
            'reference_status' => 'resolved',
            'skip_module' => true,
        ], $flow['settings']);
        $this->assertStringNotContainsString('switch-ring-group-device', json_encode($flow));
        $this->assertStringNotContainsString('private-media-id', json_encode($flow));
        $this->assertStringNotContainsString('secret', json_encode($flow));

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
                    'weight' => 50,
                ]],
            ],
            'children' => [],
        ]);

        $this->assertFalse($unsafe['settings']['supported_configuration']);
        $this->assertSame([], $unsafe['settings']['endpoints']);
    }
}
