<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Services\CallflowReferenceResolver;
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
            'data' => [],
            'children' => [],
        ]);

        $this->assertSame(['service_mode' => true], $flow['settings']);
    }
}
