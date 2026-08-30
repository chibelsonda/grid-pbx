<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Services\CallflowTreeNodeWriteValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CallflowTreeNodeWriteValidatorTest extends TestCase
{
    #[Test]
    public function it_rejects_fixed_children_under_absolute_caller_id_checks(): void
    {
        $callflow = new SwitchCallflow;
        $callflow->forceFill([
            'flow_structure' => [
                'module' => 'check_cid',
                'reference_status' => 'not_applicable',
                'settings' => ['regex' => '.*', 'use_absolute_mode' => true],
                'children' => [],
            ],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Absolute-mode caller ID branches are preserved');

        app(CallflowTreeNodeWriteValidator::class)->assertCanCreate(
            $callflow,
            [],
            'match',
            'user',
        );
    }
}
