<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Services\CallflowPublicTreeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CallflowPublicTreeServiceTest extends TestCase
{
    #[Test]
    public function it_exposes_switch_aligned_drop_capabilities_without_raw_branch_rules(): void
    {
        $tree = app(CallflowPublicTreeService::class)->transform([
            'module' => 'menu',
            'reference_status' => 'resolved',
            'children' => [
                '_' => ['module' => 'response', 'children' => []],
                '0' => ['module' => 'receive_fax', 'children' => []],
            ],
        ]);

        $children = (array) $tree['children'];

        $this->assertSame([
            'accepts_children' => true,
            'default_branch_available' => false,
            'branch_mode' => 'menu',
            'reason' => null,
        ], $tree['drop_capability']);
        $this->assertSame([
            'accepts_children' => false,
            'default_branch_available' => false,
            'branch_mode' => 'terminal',
            'reason' => 'This Switch action is terminal and cannot accept another action.',
        ], $children['_']['drop_capability']);
        $this->assertSame('terminal', $children['0']['drop_capability']['branch_mode']);
    }

    #[Test]
    public function it_explains_unresolved_and_occupied_drop_destinations(): void
    {
        $unresolved = app(CallflowPublicTreeService::class)->transform([
            'module' => 'device',
            'reference_status' => 'unresolved',
            'children' => [],
        ]);
        $occupied = app(CallflowPublicTreeService::class)->transform([
            'module' => 'user',
            'reference_status' => 'resolved',
            'children' => [
                '_' => ['module' => 'voicemail', 'children' => []],
            ],
        ]);

        $this->assertFalse($unresolved['drop_capability']['accepts_children']);
        $this->assertSame(
            'Resolve this action reference before attaching another action.',
            $unresolved['drop_capability']['reason'],
        );
        $this->assertFalse($occupied['drop_capability']['accepts_children']);
        $this->assertSame(
            'All editable branches on this Switch action are occupied.',
            $occupied['drop_capability']['reason'],
        );
    }

    #[Test]
    public function it_allows_response_below_empty_set_cav_without_risking_an_existing_subtree(): void
    {
        $empty = app(CallflowPublicTreeService::class)->transform([
            'module' => 'set_variables',
            'reference_status' => 'not_applicable',
            'children' => [],
        ]);
        $occupied = app(CallflowPublicTreeService::class)->transform([
            'module' => 'set_variables',
            'reference_status' => 'not_applicable',
            'children' => [
                '_' => ['module' => 'device', 'children' => []],
            ],
        ]);

        $this->assertTrue($empty['drop_capability']['accepts_children']);
        $this->assertTrue($empty['drop_capability']['default_branch_available']);
        $this->assertFalse($occupied['drop_capability']['accepts_children']);
        $this->assertSame(
            'Set CAV already has a next step. Remove or move it before attaching another action.',
            $occupied['drop_capability']['reason'],
        );
    }

    #[Test]
    public function it_exposes_known_caller_id_branches_and_preserves_unknown_keys(): void
    {
        $tree = app(CallflowPublicTreeService::class)->transform([
            'module' => 'check_cid',
            'children' => [
                'match' => ['module' => 'user', 'children' => []],
                'nomatch' => ['module' => 'voicemail', 'children' => []],
                '+15551234567' => ['module' => 'device', 'children' => []],
            ],
        ]);

        $children = (array) $tree['children'];

        $this->assertSame('Caller ID matches', $children['match']['branch']['label']);
        $this->assertSame('condition', $children['match']['branch']['kind']);
        $this->assertSame('Caller ID does not match', $children['nomatch']['branch']['label']);
        $this->assertSame('Preserved branch 1', $children['preserved_1']['branch']['label']);
        $this->assertArrayNotHasKey('+15551234567', $children);
    }

    #[Test]
    public function it_exposes_only_supported_call_priority_branches(): void
    {
        $supported = app(CallflowPublicTreeService::class)->transform([
            'module' => 'branch_variable',
            'settings' => ['supported_variable' => true],
            'children' => [
                '42' => ['module' => 'user', 'children' => []],
                '256' => ['module' => 'device', 'children' => []],
                '007' => ['module' => 'voicemail', 'children' => []],
                '_' => ['module' => 'hangup', 'children' => []],
            ],
        ]);

        $children = (array) $supported['children'];
        $this->assertSame('Priority 42', $children['42']['branch']['label']);
        $this->assertSame('condition', $children['42']['branch']['kind']);
        $this->assertArrayHasKey('_', $children);
        $this->assertArrayHasKey('preserved_1', $children);
        $this->assertArrayHasKey('preserved_2', $children);

        $unsupported = app(CallflowPublicTreeService::class)->transform([
            'module' => 'branch_variable',
            'settings' => ['supported_variable' => false],
            'children' => ['42' => ['module' => 'user', 'children' => []]],
        ]);

        $this->assertArrayHasKey('preserved_1', (array) $unsupported['children']);
    }

    #[Test]
    public function it_exposes_call_forward_as_an_editable_continuation_node(): void
    {
        $tree = app(CallflowPublicTreeService::class)->transform([
            'module' => 'call_forward',
            'settings' => ['action' => 'activate', 'skip_module' => false],
            'children' => [
                '_' => ['module' => 'user', 'children' => []],
            ],
        ]);

        $this->assertSame('continuation', $tree['drop_capability']['branch_mode']);
        $this->assertFalse($tree['drop_capability']['accepts_children']);
        $this->assertSame(
            'All editable branches on this Switch action are occupied.',
            $tree['drop_capability']['reason'],
        );
        $this->assertArrayHasKey('_', (array) $tree['children']);
        $this->assertSame('user', ((array) $tree['children'])['_']['module']);
    }

    #[Test]
    public function it_locks_high_risk_terminal_nodes_and_preserves_their_subtrees(): void
    {
        foreach (['pivot', 'disa', 'offnet', 'resources'] as $module) {
            $tree = app(CallflowPublicTreeService::class)->transform([
                'module' => $module,
                'settings' => null,
                'children' => [
                    '_' => ['module' => 'user', 'children' => []],
                ],
            ]);

            $this->assertSame('terminal', $tree['drop_capability']['branch_mode']);
            $this->assertFalse($tree['drop_capability']['accepts_children']);
            $this->assertSame(
                'This Switch action is terminal and cannot accept another action.',
                $tree['drop_capability']['reason'],
            );
            $this->assertNull($tree['settings']);
            $this->assertArrayHasKey('preserved_1', (array) $tree['children']);
            $this->assertSame('user', ((array) $tree['children'])['preserved_1']['module']);
        }
    }

    #[Test]
    public function it_exposes_supported_high_risk_actions_as_continuation_nodes(): void
    {
        foreach (['webhook', 'dynamic_cid'] as $module) {
            $tree = app(CallflowPublicTreeService::class)->transform([
                'module' => $module,
                'settings' => null,
                'children' => [
                    '_' => ['module' => 'user', 'children' => []],
                ],
            ]);

            $this->assertSame('continuation', $tree['drop_capability']['branch_mode']);
            $this->assertFalse($tree['drop_capability']['accepts_children']);
            $this->assertSame(
                'All editable branches on this Switch action are occupied.',
                $tree['drop_capability']['reason'],
            );
            $this->assertNull($tree['settings']);
            $this->assertArrayHasKey('_', (array) $tree['children']);
            $this->assertSame('user', ((array) $tree['children'])['_']['module']);
        }
    }

    #[Test]
    public function it_locks_acdc_agent_nodes_and_preserves_their_subtrees(): void
    {
        $tree = app(CallflowPublicTreeService::class)->transform([
            'module' => 'acdc_agent',
            'settings' => ['action' => 'login', 'skip_module' => false],
            'children' => [
                '_' => ['module' => 'user', 'children' => []],
            ],
        ]);

        $this->assertSame('locked', $tree['drop_capability']['branch_mode']);
        $this->assertSame(
            'This action is not supported by the guided callflow editor.',
            $tree['drop_capability']['reason'],
        );
        $this->assertArrayHasKey('preserved_1', (array) $tree['children']);
        $this->assertSame('user', ((array) $tree['children'])['preserved_1']['module']);
    }

    #[Test]
    public function it_locks_eavesdrop_nodes_and_preserves_their_subtrees(): void
    {
        foreach (['eavesdrop', 'eavesdrop_feature'] as $module) {
            $tree = app(CallflowPublicTreeService::class)->transform([
                'module' => $module,
                'settings' => ['skip_module' => false],
                'children' => [
                    '_' => ['module' => 'user', 'children' => []],
                ],
            ]);

            $this->assertSame('locked', $tree['drop_capability']['branch_mode']);
            $this->assertSame(
                'This action is not supported by the guided callflow editor.',
                $tree['drop_capability']['reason'],
            );
            $this->assertArrayHasKey('preserved_1', (array) $tree['children']);
            $this->assertSame('user', ((array) $tree['children'])['preserved_1']['module']);
        }
    }
}
