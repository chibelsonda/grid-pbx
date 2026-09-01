<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Agents;

use GridPbx\Switch\Domains\Agents\Dto\AgentAvailabilitySnapshot;
use GridPbx\Switch\Domains\Agents\Dto\AgentQueueMembershipWriteData;
use GridPbx\Switch\Domains\Agents\Dto\AgentSnapshot;
use GridPbx\Switch\Domains\Agents\Dto\AgentStatisticsSnapshot;
use GridPbx\Switch\Domains\Agents\Dto\AgentStatusSnapshot;
use GridPbx\Switch\Domains\Agents\Dto\AgentStatusWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class AgentResourceClient
{
    public function __construct(private SwitchClient $client) {}

    /** @return list<AgentSnapshot> */
    public function all(string $accountId): array
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request('GET', sprintf('accounts/%s/agents', rawurlencode($accountId)));
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch agent collection response data must be an array.');
        }

        return array_map(
            static fn (mixed $agent): AgentSnapshot => is_array($agent)
                ? new AgentSnapshot($agent)
                : throw new InvalidSwitchPayloadException('Switch agent collection entries must be objects.'),
            array_values($data),
        );
    }

    public function get(string $accountId, string $userId): AgentSnapshot
    {
        return $this->snapshot($this->client->request('GET', $this->path($accountId, $userId)));
    }

    public function status(string $accountId, string $userId): AgentStatusSnapshot
    {
        $payload = $this->client->request('GET', $this->path($accountId, $userId).'/status');
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch agent status response data must be an object.');
        }

        return new AgentStatusSnapshot($data);
    }

    public function statistics(string $accountId): AgentStatisticsSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request('GET', sprintf('accounts/%s/agents/stats', rawurlencode($accountId)));
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch agent statistics response data must be an object.');
        }

        return new AgentStatisticsSnapshot($data);
    }

    public function availability(string $accountId): AgentAvailabilitySnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request('GET', sprintf('accounts/%s/agents/status', rawurlencode($accountId)));
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch agent availability response data must be an object.');
        }

        return new AgentAvailabilitySnapshot($data);
    }

    public function updateStatus(string $accountId, string $userId, AgentStatusWriteData $status): void
    {
        $this->client->request('POST', $this->path($accountId, $userId).'/status', [
            'json' => ['data' => $status->toSwitchData()],
        ]);
    }

    /** @return list<string> */
    public function queueIds(string $accountId, string $userId): array
    {
        $payload = $this->client->request('GET', $this->path($accountId, $userId).'/queue_status');
        $data = $payload['data'] ?? null;

        if (! is_array($data) || array_filter($data, static fn (mixed $id): bool => ! is_string($id) || $id === '')) {
            throw new InvalidSwitchPayloadException('Switch agent queue response must contain queue identifiers.');
        }

        return array_values($data);
    }

    /** @return list<string> */
    public function updateQueueMembership(string $accountId, string $userId, AgentQueueMembershipWriteData $membership): array
    {
        $payload = $this->client->request('POST', $this->path($accountId, $userId).'/queue_status', [
            'json' => ['data' => $membership->toSwitchData()],
        ]);
        $data = $payload['data'] ?? null;

        if (! is_array($data) || array_filter($data, static fn (mixed $id): bool => ! is_string($id) || $id === '')) {
            throw new InvalidSwitchPayloadException('Switch agent queue response must contain queue identifiers.');
        }

        return array_values($data);
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): AgentSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch agent response data must be an object.');
        }

        return new AgentSnapshot($data);
    }

    private function path(string $accountId, string $userId): string
    {
        return sprintf('accounts/%s/agents/%s', rawurlencode($this->requiredIdentifier($accountId, 'account')), rawurlencode($this->requiredIdentifier($userId, 'user')));
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
