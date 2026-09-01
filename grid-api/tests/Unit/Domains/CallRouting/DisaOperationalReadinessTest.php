<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Dto\DisaOperationalReadiness;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DisaOperationalReadinessTest extends TestCase
{
    #[Test]
    public function it_requires_every_runtime_control_and_an_inactive_emergency_stop(): void
    {
        $ready = DisaOperationalReadiness::available('carrier-sbc');

        $this->assertTrue($ready->ready());
        $this->assertTrue($ready->toPublicArray()['emergency_stop_available']);
        $this->assertFalse($ready->toPublicArray()['emergency_stop_active']);

        $missingLockout = new DisaOperationalReadiness(
            adapter: 'carrier-sbc',
            ingressGuardAvailable: true,
            persistentLockoutAvailable: false,
            rateLimitAvailable: true,
            concurrencyLimitAvailable: true,
            destinationPolicyAvailable: true,
            redactedMonitoringAvailable: true,
            emergencyStopAvailable: true,
            emergencyStopActive: false,
            reason: 'Persistent lockout is unavailable.',
        );

        $this->assertFalse($missingLockout->ready());
    }

    #[Test]
    public function the_safe_default_is_stopped_and_discloses_no_private_configuration(): void
    {
        $status = DisaOperationalReadiness::unavailable()->toPublicArray();

        $this->assertFalse($status['ready']);
        $this->assertTrue($status['emergency_stop_active']);
        $this->assertSame('unconfigured', $status['adapter']);
        $this->assertArrayNotHasKey('pin', $status);
    }
}
