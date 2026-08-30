<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Services\CallflowPublicTreeService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CallflowPublicTreeServiceTest extends TestCase
{
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
}
