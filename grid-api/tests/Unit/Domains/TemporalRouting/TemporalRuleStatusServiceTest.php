<?php

namespace Tests\Unit\Domains\TemporalRouting;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Services\TemporalRuleStatusService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TemporalRuleStatusServiceTest extends TestCase
{
    public function test_it_evaluates_a_weekly_window_in_the_account_timezone(): void
    {
        $account = new SwitchAccount(['timezone' => 'Asia/Manila']);
        $rule = new SwitchTemporalRule([
            'cycle' => 'weekly',
            'interval' => 1,
            'start_date' => '2026-08-01',
            'time_window_start' => 9 * 3600,
            'time_window_stop' => 17 * 3600,
            'weekdays' => ['monday'],
            'enabled' => null,
        ]);
        $service = new TemporalRuleStatusService;

        $active = $service->rule($account, $rule, CarbonImmutable::parse('2026-08-31 10:00:00', 'Asia/Manila'));
        $inactive = $service->rule($account, $rule, CarbonImmutable::parse('2026-08-31 18:00:00', 'Asia/Manila'));

        $this->assertTrue($active['is_active']);
        $this->assertSame('scheduled', $active['override']);
        $this->assertSame('Asia/Manila', $active['timezone']);
        $this->assertFalse($inactive['is_active']);
    }

    public function test_explicit_overrides_take_precedence_over_the_schedule(): void
    {
        $account = new SwitchAccount(['timezone' => 'UTC']);
        $rule = new SwitchTemporalRule([
            'cycle' => 'date',
            'start_date' => '2030-01-01',
            'enabled' => true,
        ]);

        $status = (new TemporalRuleStatusService)->rule($account, $rule, CarbonImmutable::parse('2026-08-28 12:00:00', 'UTC'));

        $this->assertTrue($status['is_active']);
        $this->assertSame('forced_active', $status['override']);
    }
}
