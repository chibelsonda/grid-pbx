<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Services\CallflowInlineNodeDataValidator;
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
            ['action' => 'update', 'skip_module' => false],
            $validator->validate('call_forward', ['action' => 'update', 'skip_module' => false]),
        );
    }
}
