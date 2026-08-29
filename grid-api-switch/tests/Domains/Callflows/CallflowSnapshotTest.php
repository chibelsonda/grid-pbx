<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use PHPUnit\Framework\TestCase;

final class CallflowSnapshotTest extends TestCase
{
    public function test_it_derives_a_safe_structural_summary_from_the_nested_flow(): void
    {
        $snapshot = new CallflowSnapshot([
            'id' => 'callflow-1',
            'name' => 'Main incoming route',
            'numbers' => ['+15551234567'],
            'patterns' => ['^100[0-9]$'],
            'flags' => ['managed'],
            'featurecode' => ['name' => 'Do Not Disturb', 'number' => '*78'],
            'flow' => [
                'module' => 'ring_group',
                'data' => ['endpoints' => [['id' => 'private-switch-id']]],
                'children' => [
                    '_' => [
                        'module' => 'voicemail',
                        'data' => ['id' => 'private-voicemail-id'],
                        'children' => [],
                    ],
                    'timeout' => [
                        'module' => 'play',
                        'data' => ['id' => 'private-media-id'],
                        'children' => [
                            '_' => ['module' => 'hangup', 'data' => [], 'children' => []],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame(['ring_group', 'voicemail', 'play', 'hangup'], $snapshot->modules);
        self::assertSame(4, $snapshot->nodeCount);
        self::assertSame(3, $snapshot->maxDepth);
        self::assertSame('Do Not Disturb', $snapshot->featureCodeName);
        self::assertSame('*78', $snapshot->featureCodeNumber);
        self::assertSame([
            'module' => 'ring_group',
            'children' => [
                '_' => ['module' => 'voicemail', 'children' => []],
                'timeout' => [
                    'module' => 'play',
                    'children' => [
                        '_' => ['module' => 'hangup', 'children' => []],
                    ],
                ],
            ],
        ], $snapshot->flow?->toArray());
        self::assertSame('private-switch-id', $snapshot->toArray()['flow']['data']['endpoints'][0]['id']);
    }

    public function test_it_rejects_a_malformed_child_instead_of_silently_losing_a_branch(): void
    {
        $this->expectException(InvalidSwitchPayloadException::class);
        $this->expectExceptionMessage('child branches');

        new CallflowSnapshot([
            'id' => 'callflow-1',
            'flow' => ['module' => 'user', 'data' => [], 'children' => ['_' => 'invalid']],
        ]);
    }
}
