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
    public function it_allows_a_guided_public_subtree_to_be_removed(): void
    {
        $callflow = new SwitchCallflow;
        $callflow->forceFill([
            'flow_structure' => [
                'module' => 'menu',
                'reference_status' => 'resolved',
                'children' => [
                    '1' => [
                        'module' => 'user',
                        'reference_status' => 'resolved',
                        'branch' => ['key' => '1', 'label' => 'Key 1', 'kind' => 'key'],
                        'children' => [],
                    ],
                ],
            ],
        ]);

        app(CallflowTreeNodeWriteValidator::class)->assertCanDelete($callflow, ['1']);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_rejects_removing_the_root_action(): void
    {
        $callflow = new SwitchCallflow;
        $callflow->forceFill([
            'flow_structure' => [
                'module' => 'menu',
                'reference_status' => 'resolved',
                'children' => [],
            ],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The root callflow action cannot be removed.');

        app(CallflowTreeNodeWriteValidator::class)->assertCanDelete($callflow, []);
    }

    #[Test]
    public function it_accepts_an_empty_menu_key_branch_at_a_nested_path(): void
    {
        $callflow = new SwitchCallflow;
        $callflow->forceFill([
            'flow_structure' => [
                'module' => 'user',
                'reference_status' => 'resolved',
                'children' => [
                    '_' => [
                        'module' => 'menu',
                        'reference_status' => 'resolved',
                        'children' => [
                            '1' => [
                                'module' => 'hangup',
                                'reference_status' => 'not_applicable',
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        app(CallflowTreeNodeWriteValidator::class)->assertCanCreate(
            $callflow,
            ['_'],
            'timeout',
            'voicemail',
        );

        $this->addToAssertionCount(1);
    }

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
        $this->expectExceptionMessage('This conditional action has preserved branches that cannot be edited.');

        app(CallflowTreeNodeWriteValidator::class)->assertCanCreate(
            $callflow,
            [],
            'match',
            'user',
        );
    }
}
