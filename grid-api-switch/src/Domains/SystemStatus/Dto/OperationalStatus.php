<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\SystemStatus\Dto;

final readonly class OperationalStatus
{
    public function __construct(
        public bool $presenceSubscriptionDiagnosticsAvailable,
        public bool $parkedCallSummaryAvailable,
        public ?int $activeParkedCallCount,
    ) {}

    /** @return array{presence_subscription_diagnostics_available: bool, parked_call_summary_available: bool, active_parked_call_count: int|null} */
    public function toArray(): array
    {
        return [
            'presence_subscription_diagnostics_available' => $this->presenceSubscriptionDiagnosticsAvailable,
            'parked_call_summary_available' => $this->parkedCallSummaryAvailable,
            'active_parked_call_count' => $this->activeParkedCallCount,
        ];
    }
}
