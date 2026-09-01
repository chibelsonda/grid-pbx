<?php

namespace App\Domains\CallRouting\Dto;

final readonly class DisaOperationalReadiness
{
    public function __construct(
        public string $adapter,
        public bool $ingressGuardAvailable,
        public bool $persistentLockoutAvailable,
        public bool $rateLimitAvailable,
        public bool $concurrencyLimitAvailable,
        public bool $destinationPolicyAvailable,
        public bool $redactedMonitoringAvailable,
        public bool $emergencyStopAvailable,
        public bool $emergencyStopActive,
        public ?string $reason,
    ) {}

    public static function unavailable(?string $reason = null): self
    {
        return new self(
            adapter: 'unconfigured',
            ingressGuardAvailable: false,
            persistentLockoutAvailable: false,
            rateLimitAvailable: false,
            concurrencyLimitAvailable: false,
            destinationPolicyAvailable: false,
            redactedMonitoringAvailable: false,
            emergencyStopAvailable: false,
            emergencyStopActive: true,
            reason: $reason ?? 'A DISA carrier/SBC operational guard is not connected to GridPBX.',
        );
    }

    public static function available(string $adapter): self
    {
        return new self(
            adapter: $adapter,
            ingressGuardAvailable: true,
            persistentLockoutAvailable: true,
            rateLimitAvailable: true,
            concurrencyLimitAvailable: true,
            destinationPolicyAvailable: true,
            redactedMonitoringAvailable: true,
            emergencyStopAvailable: true,
            emergencyStopActive: false,
            reason: null,
        );
    }

    public function ready(): bool
    {
        return $this->ingressGuardAvailable
            && $this->persistentLockoutAvailable
            && $this->rateLimitAvailable
            && $this->concurrencyLimitAvailable
            && $this->destinationPolicyAvailable
            && $this->redactedMonitoringAvailable
            && $this->emergencyStopAvailable
            && ! $this->emergencyStopActive;
    }

    /** @return array<string, bool|string|null> */
    public function toPublicArray(): array
    {
        return [
            'ready' => $this->ready(),
            'adapter' => $this->adapter,
            'ingress_guard_available' => $this->ingressGuardAvailable,
            'persistent_lockout_available' => $this->persistentLockoutAvailable,
            'rate_limit_available' => $this->rateLimitAvailable,
            'concurrency_limit_available' => $this->concurrencyLimitAvailable,
            'destination_policy_available' => $this->destinationPolicyAvailable,
            'redacted_monitoring_available' => $this->redactedMonitoringAvailable,
            'emergency_stop_available' => $this->emergencyStopAvailable,
            'emergency_stop_active' => $this->emergencyStopActive,
            'reason' => $this->reason,
        ];
    }
}
