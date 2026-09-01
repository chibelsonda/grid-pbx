<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Callflows\Dto\CallflowEntryPointsWriteData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CallflowEntryPointsWriteDataTest extends TestCase
{
    public function test_it_replaces_only_known_entry_numbers_and_preserves_the_callflow_tree(): void
    {
        $flow = [
            'module' => 'menu',
            'data' => ['id' => 'menu-1'],
            'children' => [
                '1' => ['module' => 'user', 'data' => ['id' => 'user-1'], 'children' => []],
            ],
        ];

        $payload = new CallflowEntryPointsWriteData(
            current: [
                'id' => 'callflow-1',
                'pvt_account_id' => 'account-1',
                'name' => 'Main IVR',
                'numbers' => ['2001', '2999', '*97'],
                'patterns' => ['^\\+1555'],
                'flow' => $flow,
            ],
            assignedEntryNumbers: ['3000'],
            knownEntryNumbers: ['2999'],
        );

        self::assertSame([
            'name' => 'Main IVR',
            'numbers' => ['2001', '*97', '3000'],
            'patterns' => ['^\\+1555'],
            'flow' => $flow,
        ], $payload->toSwitchData());
    }

    public function test_it_requires_an_existing_callflow_tree(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CallflowEntryPointsWriteData(
            current: ['numbers' => ['2999']],
            assignedEntryNumbers: ['3000'],
            knownEntryNumbers: ['2999'],
        );
    }
}
