<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Services\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;

final readonly class ServiceLimitsSnapshot extends EntitySnapshot
{
    public bool $enabled;

    public bool $allowPrepay;

    public bool $allowPostpay;

    public int $inboundTrunks;

    public int $outboundTrunks;

    public int $twowayTrunks;

    public int $burstTrunks;

    public ?int $calls;

    public ?int $resourceConsumingCalls;

    public bool $softLimitInbound;

    public bool $softLimitOutbound;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->enabled = (bool) ($data['enabled'] ?? true);
        $this->allowPrepay = (bool) ($data['allow_prepay'] ?? true);
        $this->allowPostpay = (bool) ($data['allow_postpay'] ?? false);
        $this->inboundTrunks = $this->nonNegativeInt($data['inbound_trunks'] ?? 0);
        $this->outboundTrunks = $this->nonNegativeInt($data['outbound_trunks'] ?? 0);
        $this->twowayTrunks = $this->nonNegativeInt($data['twoway_trunks'] ?? 0);
        $this->burstTrunks = $this->nonNegativeInt($data['burst_trunks'] ?? 0);
        $this->calls = is_numeric($data['calls'] ?? null) ? $this->nonNegativeInt($data['calls']) : null;
        $this->resourceConsumingCalls = is_numeric($data['resource_consuming_calls'] ?? null) ? $this->nonNegativeInt($data['resource_consuming_calls']) : null;
        $this->softLimitInbound = (bool) ($data['soft_limit_inbound'] ?? false);
        $this->softLimitOutbound = (bool) ($data['soft_limit_outbound'] ?? false);
    }

    private function nonNegativeInt(mixed $value): int
    {
        return max(0, (int) $value);
    }
}
