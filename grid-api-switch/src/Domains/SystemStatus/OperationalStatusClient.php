<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\SystemStatus;

use GridPbx\Switch\Domains\SystemStatus\Dto\OperationalStatus;
use GridPbx\Switch\Shared\Exceptions\SwitchException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class OperationalStatusClient
{
    public function __construct(private SwitchClient $client) {}

    public function inspect(string $accountId): OperationalStatus
    {
        if ($accountId === '') {
            throw new InvalidArgumentException('Switch account identifier is required.');
        }

        $accountPath = sprintf('accounts/%s', rawurlencode($accountId));
        $presenceAvailable = $this->presenceAvailable($accountPath.'/presence');
        [$parkingAvailable, $activeParkedCallCount] = $this->parkingSummary(
            $accountPath.'/parked_calls',
        );

        return new OperationalStatus(
            presenceSubscriptionDiagnosticsAvailable: $presenceAvailable,
            parkedCallSummaryAvailable: $parkingAvailable,
            activeParkedCallCount: $activeParkedCallCount,
        );
    }

    private function presenceAvailable(string $path): bool
    {
        try {
            $payload = $this->client->request('GET', $path);

            return is_array($payload['data'] ?? null);
        } catch (SwitchException) {
            return false;
        }
    }

    /** @return array{bool, int|null} */
    private function parkingSummary(string $path): array
    {
        try {
            $payload = $this->client->request('GET', $path);
            $data = $payload['data'] ?? null;
            $slots = is_array($data) ? ($data['slots'] ?? null) : null;

            return is_array($slots) ? [true, count($slots)] : [false, null];
        } catch (SwitchException) {
            return [false, null];
        }
    }
}
