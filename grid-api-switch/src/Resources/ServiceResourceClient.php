<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use GridPbx\Switch\Dto\Services\ServiceLimitsSnapshot;
use GridPbx\Switch\Dto\Services\ServiceSummarySnapshot;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class ServiceResourceClient
{
    public function __construct(private SwitchClient $client) {}

    public function summary(string $accountId): ServiceSummarySnapshot
    {
        $data = $this->data($this->client->request('GET', $this->path($accountId, 'services/summary')), 'summary');

        return new ServiceSummarySnapshot($data);
    }

    public function limits(string $accountId): ServiceLimitsSnapshot
    {
        $data = $this->data($this->client->request('GET', $this->path($accountId, 'limits')), 'limits');

        return new ServiceLimitsSnapshot($data);
    }

    private function path(string $accountId, string $resource): string
    {
        if ($accountId === '') {
            throw new InvalidArgumentException('Switch account identifier is required.');
        }

        return sprintf('accounts/%s/%s', rawurlencode($accountId), $resource);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function data(array $payload, string $resource): array
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException("Switch service {$resource} response data must be an object.");
        }

        return $data;
    }
}
